/*
 * PHPStanTurbo\ClosureBindArgVisitor — native twin of
 * PHPStan\Parser\ClosureBindArgVisitor, declared under that name at
 * activation (final, extending PhpParser\NodeVisitorAbstract like the
 * original).
 *
 * Marks the closure argument of a Closure::bind() call that also passes a
 * new $this (each argument found by position or by name), and annotates the
 * self/parent/static names inside an inline closure/arrow function bound
 * that way with the call's scope argument — a top-first stack of the scope
 * arguments of the enclosing bound closures (null for the default "static"
 * scope), entered and left at the closure nodes recorded by object id in
 * $boundClosures.
 *
 * enterNode()/leaveNode() always return null and beforeTraverse() only
 * resets the per-file state, so the visitor is also registered with
 * pt_native_visitor_register(): the native NodeTraverser then runs it
 * directly per node instead of calling into the engine (ParserVisitors.h).
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
	/* beforeTraverse(); the twin only resets the per-file state and returns
	 * null */
	static void beforeTraverse(zend_object *visitor)
	{
		zv::ObjRef(visitor).propAtWrite(slots::scopeStack, zv::Arr::empty());
		zv::ObjRef(visitor).propAtWrite(slots::boundClosures, zv::Arr::empty());
	}

	/* enterNode(); the twin always returns null, false = pending exception */
	[[nodiscard]] static bool enterNode(zend_object *visitor, zend_object *node)
	{
		zval *args = NULL;
		if (!isClosureBindCall(node, &args)) {
			if (UNEXPECTED(EG(exception))) return false;
		} else {
			BindArgs bindArgs;
			findBindArgs(args, bindArgs);
			if (bindArgs.closure != NULL) {
				if (bindArgs.newThis != NULL) {
					visitors::setAttributeTrue(bindArgs.closure, pt_closure_bind_arg_attribute_str);
					if (UNEXPECTED(EG(exception))) return false;
				}

				zval *closure = argValue(bindArgs.closure);
				if (closure != NULL && Z_TYPE_P(closure) == IS_OBJECT
					&& (visitors::isInstanceOf(Z_OBJ_P(closure), PT_CLASS_CLOSURE_EXPR) || visitors::isInstanceOf(Z_OBJ_P(closure), PT_CLASS_ARROW_FUNCTION))) {
					/* $this->boundClosures[spl_object_id($closure)] =
					 * $newScopeArg !== null ? $newScopeArg->value : null —
					 * null means default scope "static" */
					zval scope;
					ZVAL_NULL(&scope);
					if (bindArgs.newScope != NULL) {
						zval *value = argValue(bindArgs.newScope);
						if (value != NULL) {
							ZVAL_COPY_VALUE(&scope, value);
						}
					}
					bindClosure(visitor, Z_OBJ_P(closure), &scope);
				}
			}
		}

		zval *boundScope = boundScopeOf(visitor, node);
		if (boundScope != NULL) {
			visitors::unshiftStack(stackOf(visitor), boundScope);
		}

		return annotateName(visitor, node);
	}

	/* leaveNode(); the twin always returns null, false = pending exception */
	[[nodiscard]] static bool leaveNode(zend_object *visitor, zend_object *node)
	{
		if (boundScopeOf(visitor, node) != NULL) {
			visitors::shiftStack(stackOf(visitor));
		}
		return true;
	}

private:
	/* the $closureArg / $newThisArg / $newScopeArg the twin picks out of the
	 * call's arguments; NULL for null */
	struct BindArgs
	{
		zend_object *closure = NULL;
		zend_object *newThis = NULL;
		zend_object *newScope = NULL;
	};

	/*
	 * foreach ($node->getArgs() as $i => $arg): an unnamed argument by its
	 * position (0, 1, 2), a named one by its name (closure, newThis,
	 * newScope); a later match replaces an earlier one
	 */
	static void findBindArgs(zval *args, BindArgs &out)
	{
		static NodeProp argNameProp = PT_NODE_PROP(PT_CLASS_ARG, "name");
		static NodeProp identifierProp = PT_IDENTIFIER_PROP;

		if (args == NULL || Z_TYPE_P(args) != IS_ARRAY) return;
		for (auto entry : zv::ArrRef(args)) {
			zv::Ref value = entry.value().deref();
			if (!value.isObject()) continue;
			zend_object *arg = value.asObject();
			zend_object *name = visitors::isInstanceOf(arg, PT_CLASS_ARG) ? argNameProp.objectOf(arg, PT_CLASS_IDENTIFIER) : NULL;
			if (name == NULL) {
				if (entry.hasStringKey()) continue;
				zend_ulong i = entry.indexKey();
				if (i == 0) {
					out.closure = arg;
				} else if (i == 1) {
					out.newThis = arg;
				} else if (i == 2) {
					out.newScope = arg;
				}
				continue;
			}

			/* $arg->name->toString() */
			zend_string *argName = visitors::nameString(name, identifierProp);
			if (argName == NULL) continue;
			if (zend_string_equals_literal(argName, "closure")) {
				out.closure = arg;
			} else if (zend_string_equals_literal(argName, "newThis")) {
				out.newThis = arg;
			} else if (zend_string_equals_literal(argName, "newScope")) {
				out.newScope = arg;
			}
		}
	}

	/* $arg->value of an Arg; NULL for anything else */
	static zval *argValue(zend_object *arg)
	{
		static NodeProp argValueProp = PT_NODE_PROP(PT_CLASS_ARG, "value");

		return visitors::isInstanceOf(arg, PT_CLASS_ARG) ? argValueProp.of(arg) : NULL;
	}

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

	static zval *boundClosuresOf(zend_object *visitor)
	{
		return zv::ObjRef(visitor).propAt(slots::boundClosures).deref().raw();
	}

	/* $this->boundClosures[spl_object_id($node)] when array_key_exists(),
	 * NULL otherwise (a null entry is the default "static" scope, not
	 * absence) */
	static zval *boundScopeOf(zend_object *visitor, zend_object *node)
	{
		zval *boundClosures = boundClosuresOf(visitor);
		if (UNEXPECTED(Z_TYPE_P(boundClosures) != IS_ARRAY)) return NULL;
		zval *found = zend_hash_index_find(Z_ARRVAL_P(boundClosures), (zend_ulong) node->handle);
		if (found == NULL) return NULL;
		ZVAL_DEREF(found);
		return found;
	}

	/* $this->boundClosures[spl_object_id($closure)] = $scope (borrowed) */
	static void bindClosure(zend_object *visitor, zend_object *closure, zval *scope)
	{
		zval *boundClosures = boundClosuresOf(visitor);
		if (UNEXPECTED(Z_TYPE_P(boundClosures) != IS_ARRAY)) return;
		SEPARATE_ARRAY(boundClosures);
		Z_TRY_ADDREF_P(scope);
		zend_hash_index_update(Z_ARRVAL_P(boundClosures), (zend_ulong) closure->handle, scope);
	}
};

} // namespace phpstanturbo

using phpstanturbo::ClosureBindArgVisitor;

/* {{{ engine ABI glue: parameter parsing + registration */

static const pt_native_visitor pt_closure_bind_arg_entry = {
	&pt_ce_closure_bind_arg_visitor,
	ClosureBindArgVisitor::enterNode,
	ClosureBindArgVisitor::leaveNode,
	ClosureBindArgVisitor::beforeTraverse,
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

	cls.method(sigs::beforeTraverse, [](INTERNAL_FUNCTION_PARAMETERS) {
		HashTable *nodes;
		if (!zp::parse<zp::Ht>(execute_data, nodes)) RETURN_THROWS();
		(void) nodes;
		ClosureBindArgVisitor::beforeTraverse(Z_OBJ_P(ZEND_THIS));
		RETURN_NULL();
	});

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
