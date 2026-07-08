/*
 * PHPStanTurbo\ClosureBindScopeResolver — native implementation of
 * PHPStan\Analyser\ClosureBindScopeResolver.
 *
 * A DI service (#[AutowiredService]): the constructor keeps the twin's exact
 * arginfo, so Nette autowires it by reflection. resolveScopeClass() reads the
 * scope argument ClosureBindArgVisitor annotated on a self/parent/static name
 * straight off the node's attributes array; only a name inside a bound
 * closure goes on to the scope's getType() and the reflection provider.
 * Native callers (ClassConstFetchHandler, MutatingScope) reach it through
 * pt_closure_bind_scope_resolver_resolve_scope_class() without a frame.
 */

#include "support.h"
#include "generated/ClosureBindScopeResolver.h"

namespace slots = ptdecl::ClosureBindScopeResolver::slot;
namespace sigs = ptdecl::ClosureBindScopeResolver::sig;
#include "zv.h"
#include "TypeTraits.h"
#include "TypeOps.h"
#include "Engine.h"

static zend_class_entry *pt_ce_closure_bind_scope_resolver;

namespace phpstanturbo {

/* Mirrors PHPStan\Analyser\ClosureBindScopeResolver. */
class ClosureBindScopeResolver
{
public:
	explicit ClosureBindScopeResolver(zend_object *self) : self(self) {}

	/* the constructor body: the promoted property */
	void construct(zval *reflectionProvider) const
	{
		zv::ObjRef(self).propAtWrite(slots::reflectionProvider, zv::Val::copyOf(zv::Ref(reflectionProvider)));
		Z_PROP_FLAG_P(OBJ_PROP_NUM(self, slots::reflectionProvider)) = 0; /* no longer IS_PROP_UNINIT */
	}

	/* Mirrors resolveScopeClass(); IS_NULL for null, UNDEF = pending
	 * exception */
	zv::Val resolveScopeClass(zval *scope, zval *class_) const
	{
		zv::Val scopeArg = pt_engine_node_get_attribute(Z_OBJ_P(class_), PT_LC("closureBindScope"));
		if (UNEXPECTED(scopeArg.isUndef())) return zv::Val();
		zend_class_entry *exprClass = pt_class(PT_CLASS_EXPR);
		if (UNEXPECTED(exprClass == NULL)) return zv::Val();
		if (!scopeArg.ref().instanceOf(exprClass)) {
			// Either the node is not inside a bound closure, or the attribute is null for
			// the default "static" scope. Both keep the enclosing class.
			return zv::Val::null();
		}

		zv::Val scopeArgType = pt_mutating_scope_get_type(Z_OBJ_P(scope), scopeArg.raw());
		if (UNEXPECTED(scopeArgType.isUndef())) return zv::Val();
		zv::Val classStringObjectType = pt_type_call(Z_OBJ_P(scopeArgType.raw()), PT_LC("getclassstringobjecttype"), 0, NULL);
		if (UNEXPECTED(classStringObjectType.isUndef())) return zv::Val();
		zv::Val objectClassNames = pt_type_op(Z_OBJ_P(classStringObjectType.raw()), PT_OP_GET_OBJECT_CLASS_NAMES, 0, NULL);
		if (UNEXPECTED(objectClassNames.isUndef())) return zv::Val();
		if (Z_TYPE_P(objectClassNames.raw()) != IS_ARRAY || zend_hash_num_elements(Z_ARRVAL_P(objectClassNames.raw())) != 1) return zv::Val::null();

		/* $objectClassNames[0] — the only element of the list */
		zval *className = NULL;
		for (auto entry : zv::ArrRef(objectClassNames.raw())) {
			className = entry.value().deref().raw();
		}
		zval *reflectionProvider = OBJ_PROP_NUM(self, slots::reflectionProvider);
		if (UNEXPECTED(Z_TYPE_P(reflectionProvider) != IS_OBJECT)) {
			zend_throw_error(NULL, "Typed property %s::$reflectionProvider must not be accessed before initialization", ZSTR_VAL(self->ce->name));
			return zv::Val();
		}
		bool hasClass;
		if (UNEXPECTED(!pt_reflection_provider_has_class(Z_OBJ_P(reflectionProvider), className, hasClass))) return zv::Val();
		if (!hasClass) return zv::Val::null();

		return pt_reflection_provider_get_class(Z_OBJ_P(reflectionProvider), className);
	}

private:
	zend_object *self;
};

} // namespace phpstanturbo

using phpstanturbo::ClosureBindScopeResolver;

/* {{{ direct entries (support.h) */

zv::Val pt_closure_bind_scope_resolver_resolve_scope_class(zval *resolver, zval *scope, zval *class_)
{
	if (EXPECTED(Z_TYPE_P(resolver) == IS_OBJECT && Z_OBJCE_P(resolver) == pt_ce_closure_bind_scope_resolver && Z_TYPE_P(scope) == IS_OBJECT)) {
		return ClosureBindScopeResolver(Z_OBJ_P(resolver)).resolveScopeClass(scope, class_);
	}
	if (UNEXPECTED(Z_TYPE_P(resolver) != IS_OBJECT)) {
		zend_throw_error(NULL, "Call to a member function resolveScopeClass() on %s", zend_zval_value_name(resolver));
		return zv::Val();
	}
	zv::Args argv{scope, class_};
	return pt_type_call(Z_OBJ_P(resolver), PT_LC("resolvescopeclass"), 2, argv);
}

/* }}} */

/* {{{ engine ABI glue: parameter parsing + registration */

#include "reg.h"

PT_MINIT_REGISTRATION(pt_register_closure_bind_scope_resolver)
{
	reg::Class cls("PHPStan\\Analyser\\ClosureBindScopeResolver");
	ptdecl::ClosureBindScopeResolver::declareClass(cls);
	ptdecl::ClosureBindScopeResolver::declareProperties(cls);

	/* the DI service's constructor: the generated arginfo names the twin's
	 * parameter class exactly (README rule 6) */
	cls.method(sigs::__construct, [](INTERNAL_FUNCTION_PARAMETERS) {
		zval *reflectionProvider;
		if (!zp::parse<zp::Obj>(execute_data, reflectionProvider)) RETURN_THROWS();
		ClosureBindScopeResolver(Z_OBJ_P(ZEND_THIS)).construct(reflectionProvider);
	});

	cls.method(sigs::resolveScopeClass, [](INTERNAL_FUNCTION_PARAMETERS) {
		zval *scope, *class_;
		if (!zp::parse<zp::Obj, zp::Obj>(execute_data, scope, class_)) RETURN_THROWS();
		PT_RETURN_VAL(ClosureBindScopeResolver(Z_OBJ_P(ZEND_THIS)).resolveScopeClass(scope, class_));
	});

	cls.shadow(&pt_ce_closure_bind_scope_resolver);
}

/* }}} */
