/*
 * PHPStanTurbo\ClosureBindArgVisitor — native twin of
 * PHPStan\Parser\ClosureBindArgVisitor, declared under that name at
 * activation (final, extending PhpParser\NodeVisitorAbstract like the
 * original).
 *
 * Marks the closure argument of a Closure::bind() call that also passes a
 * new $this, and annotates the self/parent/static names inside the call with
 * its scope argument — a top-first stack of the scope arguments of the
 * enclosing Closure::bind() calls (null for the default "static" scope).
 *
 * enterNode()/leaveNode() always return null, so the visitor is also
 * registered with pt_native_visitor_register(): the native NodeTraverser then
 * runs it directly per node instead of calling into the engine
 * (ParserVisitors.h).
 */

#include "ParserVisitors.h"
#include "generated/ClosureBindArgVisitor.h"

namespace sigs = ptdecl::ClosureBindArgVisitor::sig;
namespace slots = ptdecl::ClosureBindArgVisitor::slot;

static zend_class_entry *pt_ce_closure_bind_arg_visitor = nullptr;

static const char pt_closure_bind_arg_attribute[] = "closureBindArg";
static zend_string *pt_closure_bind_arg_attribute_str = nullptr;
static const char pt_closure_bind_scope_attribute[] = "closureBindScope";
static zend_string *pt_closure_bind_scope_attribute_str = nullptr;

namespace phpstanturbo {

using visitors::NodeProp;

/* Mirrors PHPStan\Parser\ClosureBindArgVisitor. */
class ClosureBindArgVisitor
{
public:
	/* enterNode(); the twin always returns null, false = pending exception */
	[[nodiscard]] static bool enterNode(zend_object *visitor, zend_object *node)
	{
		static NodeProp argValueProp = PT_NODE_PROP(PT_CLASS_ARG, "value");

		zval *args = NULL;
		if (!isClosureBindCall(node, &args)) {
			if (UNEXPECTED(EG(exception))) return false;
		} else {
			if (args != NULL && Z_TYPE_P(args) == IS_ARRAY && zend_hash_num_elements(Z_ARRVAL_P(args)) > 1) {
				zend_object *arg = visitors::argAt(args, 0);
				if (arg != NULL) {
					visitors::setAttributeTrue(arg, pt_closure_bind_arg_attribute_str);
				}
			}

			/* array_unshift($this->scopeStack, $args[2]->value ?? null) — null
			 * means default scope "static" */
			zval scope;
			ZVAL_NULL(&scope);
			zend_object *scopeArg = visitors::argAt(args, 2);
			if (scopeArg != NULL && visitors::isInstanceOf(scopeArg, PT_CLASS_ARG)) {
				zval *value = argValueProp.of(scopeArg);
				if (value != NULL) {
					ZVAL_COPY_VALUE(&scope, value);
				}
			}
			visitors::unshiftStack(stackOf(visitor), &scope);
		}

		return annotateName(visitor, node);
	}

	/* leaveNode(); the twin always returns null, false = pending exception */
	[[nodiscard]] static bool leaveNode(zend_object *visitor, zend_object *node)
	{
		zval *args = NULL;
		if (!isClosureBindCall(node, &args)) return !EG(exception);
		visitors::shiftStack(stackOf(visitor));
		return true;
	}

private:
	/*
	 * `$node instanceof StaticCall && $node->class instanceof Name &&
	 * $node->class->toLowerString() === 'closure' && $node->name instanceof
	 * Identifier && $node->name->toLowerString() === 'bind' &&
	 * !$node->isFirstClassCallable()`, plus the call's $args slot
	 */
	static bool isClosureBindCall(zend_object *node, zval **argsOut)
	{
		static NodeProp classProp = PT_NODE_PROP(PT_CLASS_STATIC_CALL, "class");
		static NodeProp methodProp = PT_NODE_PROP(PT_CLASS_STATIC_CALL, "name");
		static NodeProp argsProp = PT_NODE_PROP(PT_CLASS_STATIC_CALL, "args");
		static NodeProp nameProp = PT_NAME_PROP;
		static NodeProp identifierProp = PT_IDENTIFIER_PROP;

		if (!visitors::isInstanceOf(node, PT_CLASS_STATIC_CALL)) return false;
		zend_object *className = classProp.objectOf(node, PT_CLASS_NAME);
		if (className == NULL) return false;
		zend_string *classString = visitors::nameString(className, nameProp);
		if (classString == NULL || !visitors::lowerEquals(classString, "closure")) return false;
		zend_object *method = methodProp.objectOf(node, PT_CLASS_IDENTIFIER);
		if (method == NULL) return false;
		zend_string *methodName = visitors::nameString(method, identifierProp);
		if (methodName == NULL || !visitors::lowerEquals(methodName, "bind")) return false;
		zval *args = argsProp.of(node);
		if (visitors::isFirstClassCallable(args)) return false;
		*argsOut = args;
		return true;
	}

	/* `$node instanceof Name && array_key_exists(0, $this->scopeStack) &&
	 * $node->isSpecialClassName()`: the name gets the innermost scope argument */
	[[nodiscard]] static bool annotateName(zend_object *visitor, zend_object *node)
	{
		static NodeProp nameProp = PT_NAME_PROP;

		if (!visitors::isInstanceOf(node, PT_CLASS_NAME)) return !EG(exception);
		zval *stack = stackOf(visitor);
		if (Z_TYPE_P(stack) != IS_ARRAY) return true;
		zval *innermost = zend_hash_index_find(Z_ARRVAL_P(stack), 0);
		if (innermost == NULL) return true;
		zend_string *name = visitors::nameString(node, nameProp);
		if (name == NULL || !isSpecialClassName(name)) return !EG(exception);
		visitors::setAttribute(node, pt_closure_bind_scope_attribute_str, innermost);
		return !EG(exception);
	}

	/* Name::isSpecialClassName(): self, parent or static in any case */
	static bool isSpecialClassName(zend_string *name)
	{
		return visitors::lowerEquals(name, "self") || visitors::lowerEquals(name, "parent") || visitors::lowerEquals(name, "static");
	}

	static zval *stackOf(zend_object *visitor)
	{
		return zv::ObjRef(visitor).propAt(slots::scopeStack).deref().raw();
	}
};

} // namespace phpstanturbo

using phpstanturbo::ClosureBindArgVisitor;

/* {{{ engine ABI glue: parameter parsing + registration */

static const pt_native_visitor pt_closure_bind_arg_entry = {
	&pt_ce_closure_bind_arg_visitor,
	ClosureBindArgVisitor::enterNode,
	ClosureBindArgVisitor::leaveNode,
	NULL,
};

PT_MINIT_REGISTRATION(pt_register_closure_bind_arg_visitor)
{
	pt_closure_bind_arg_attribute_str = zend_string_init_interned(pt_closure_bind_arg_attribute, sizeof(pt_closure_bind_arg_attribute) - 1, 1);
	pt_closure_bind_scope_attribute_str = zend_string_init_interned(pt_closure_bind_scope_attribute, sizeof(pt_closure_bind_scope_attribute) - 1, 1);

	reg::Class cls("PHPStan\\Parser\\ClosureBindArgVisitor");
	ptdecl::ClosureBindArgVisitor::declareClass(cls);
	ptdecl::ClosureBindArgVisitor::declareProperties(cls);
	cls.publicClassConstantString("ATTRIBUTE_NAME", pt_closure_bind_arg_attribute);
	cls.publicClassConstantString("SCOPE_ATTRIBUTE_NAME", pt_closure_bind_scope_attribute);

	cls.method(sigs::enterNode, [](INTERNAL_FUNCTION_PARAMETERS) {
		zval *node;
		if (!zp::parse<zp::Obj>(execute_data, node)) RETURN_THROWS();
		if (UNEXPECTED(!ClosureBindArgVisitor::enterNode(Z_OBJ_P(ZEND_THIS), Z_OBJ_P(node)))) RETURN_THROWS();
		RETURN_NULL();
	});

	cls.method(sigs::leaveNode, [](INTERNAL_FUNCTION_PARAMETERS) {
		zval *node;
		if (!zp::parse<zp::Obj>(execute_data, node)) RETURN_THROWS();
		if (UNEXPECTED(!ClosureBindArgVisitor::leaveNode(Z_OBJ_P(ZEND_THIS), Z_OBJ_P(node)))) RETURN_THROWS();
		RETURN_NULL();
	});

	cls.shadow(&pt_ce_closure_bind_arg_visitor);
	pt_native_visitor_register(&pt_closure_bind_arg_entry);
}

/* }}} */
