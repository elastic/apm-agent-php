dnl config.m4 for extension elastic_apm

dnl Comments in this file start with the string 'dnl'.
dnl Remove where necessary.

dnl If your extension references something external, use with:

dnl PHP_ARG_WITH(elastic_apm, for elastic_apm support,
dnl Make sure that the comment is aligned:
dnl [  --with-elastic_apm             Include Elastic APM support])

dnl Otherwise use enable:

PHP_ARG_ENABLE(elastic_apm, whether to enable elastic_apm support,
[  --enable-elastic_apm          Enable Elastic APM support])


if test "$PHP_ELASTIC_APM" != "no"; then
  dnl Write more examples of tests here...

  dnl # get library FOO build options from pkg-config output
  dnl AC_PATH_PROG(PKG_CONFIG, pkg-config, no)
  dnl AC_MSG_CHECKING(for libfoo)
  dnl if test -x "$PKG_CONFIG" && $PKG_CONFIG --exists foo; then
  dnl   if $PKG_CONFIG foo --atleast-version 1.2.3; then
  dnl     LIBFOO_CFLAGS=\`$PKG_CONFIG foo --cflags\`
  dnl     LIBFOO_LIBDIR=\`$PKG_CONFIG foo --libs\`
  dnl     LIBFOO_VERSON=\`$PKG_CONFIG foo --modversion\`
  dnl     AC_MSG_RESULT(from pkgconfig: version $LIBFOO_VERSON)
  dnl   else
  dnl     AC_MSG_ERROR(system libfoo is too old: version 1.2.3 required)
  dnl   fi
  dnl else
  dnl   AC_MSG_ERROR(pkg-config not found)
  dnl fi
  dnl PHP_EVAL_LIBLINE($LIBFOO_LIBDIR, ELASTIC_APM_SHARED_LIBADD)
  dnl PHP_EVAL_INCLINE($LIBFOO_CFLAGS)

  dnl # --with-elastic_apm -> check with-path
  dnl SEARCH_PATH="/usr/local /usr"     # you might want to change this
  dnl SEARCH_FOR="/include/elastic_apm.h"  # you most likely want to change this
  dnl if test -r $PHP_ELASTIC_APM/$SEARCH_FOR; then # path given as parameter
  dnl   ELASTIC_APM_DIR=$PHP_ELASTIC_APM
  dnl else # search default path list
  dnl   AC_MSG_CHECKING([for elastic_apm files in default path])
  dnl   for i in $SEARCH_PATH ; do
  dnl     if test -r $i/$SEARCH_FOR; then
  dnl       ELASTIC_APM_DIR=$i
  dnl       AC_MSG_RESULT(found in $i)
  dnl     fi
  dnl   done
  dnl fi
  dnl
  dnl if test -z "$ELASTIC_APM_DIR"; then
  dnl   AC_MSG_RESULT([not found])
  dnl   AC_MSG_ERROR([Please reinstall the elastic_apm distribution])
  dnl fi

  dnl # --with-elastic_apm -> add include path
  dnl PHP_ADD_INCLUDE($ELASTIC_APM_DIR/include)

  dnl # --with-elastic_apm -> check for lib and symbol presence
  dnl LIBNAME=ELASTIC_APM # you may want to change this
  dnl LIBSYMBOL=ELASTIC_APM # you most likely want to change this

  dnl PHP_CHECK_LIBRARY($LIBNAME,$LIBSYMBOL,
  dnl [
  dnl   PHP_ADD_LIBRARY_WITH_PATH($LIBNAME, $ELASTIC_APM_DIR/$PHP_LIBDIR, ELASTIC_APM_SHARED_LIBADD)
  dnl   AC_DEFINE(HAVE_ELASTIC_APMLIB,1,[ ])
  dnl ],[
  dnl   AC_MSG_ERROR([wrong elastic_apm lib version or lib not found])
  dnl ],[
  dnl   -L$ELASTIC_APM_DIR/$PHP_LIBDIR -lm
  dnl ])
  dnl
  dnl PHP_SUBST(ELASTIC_APM_SHARED_LIBADD)

  dnl # In case of no dependencies
  AC_DEFINE(HAVE_ELASTIC_APM, 1, [ Have elastic_apm support ])

  ELASTIC_APM_PHP_EXT_SOURCES="\
    AST_instrumentation.c \
    backend_comm.c \
    ConfigManager.c \
    elastic_apm.c \
    elastic_apm_API.c \
    elastic_apm_assert.c \
    internal_checks.c \
    lifecycle.c \
    log.c \
    MemoryTracker.c \
    php_error.c \
    platform.c \
    platform_threads_linux.c \
    ResultCode.c \
    supportability.c \
    SystemMetrics.c \
    TextOutputStream.c \
    time_util.c \
    Tracer.c \
    tracer_PHP_part.c \
    util.c \
    util_for_PHP.c \
  "

  PHP_NEW_EXTENSION(elastic_apm, $ELASTIC_APM_PHP_EXT_SOURCES, $ext_shared)
  EXTRA_CFLAGS="$EXTRA_CFLAGS -pthread -Werror"
  PHP_SUBST(EXTRA_CFLAGS)
  EXTRA_LDFLAGS="$EXTRA_LDFLAGS -lcurl"
  PHP_SUBST(EXTRA_LDFLAGS)
fi
