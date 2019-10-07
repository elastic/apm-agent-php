dnl config.m4 for extension elasticapm

dnl Comments in this file start with the string 'dnl'.
dnl Remove where necessary.

dnl If your extension references something external, use with:

dnl PHP_ARG_WITH(elasticapm, for elasticapm support,
dnl Make sure that the comment is aligned:
dnl [  --with-elasticapm             Include elasticapm support])

dnl Otherwise use enable:

PHP_ARG_ENABLE(elasticapm, whether to enable elasticapm support,
[  --enable-elasticapm          Enable ElasticApm support])


if test "$PHP_ELASTICAPM" != "no"; then
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
  dnl PHP_EVAL_LIBLINE($LIBFOO_LIBDIR, ELASTICAPM_SHARED_LIBADD)
  dnl PHP_EVAL_INCLINE($LIBFOO_CFLAGS)

  dnl # --with-elasticapm -> check with-path
  dnl SEARCH_PATH="/usr/local /usr"     # you might want to change this
  dnl SEARCH_FOR="/include/elasticapm.h"  # you most likely want to change this
  dnl if test -r $PHP_ELASTICAPM/$SEARCH_FOR; then # path given as parameter
  dnl   ELASTICAPM_DIR=$PHP_ELASTICAPM
  dnl else # search default path list
  dnl   AC_MSG_CHECKING([for elasticapm files in default path])
  dnl   for i in $SEARCH_PATH ; do
  dnl     if test -r $i/$SEARCH_FOR; then
  dnl       ELASTICAPM_DIR=$i
  dnl       AC_MSG_RESULT(found in $i)
  dnl     fi
  dnl   done
  dnl fi
  dnl
  dnl if test -z "$ELASTICAPM_DIR"; then
  dnl   AC_MSG_RESULT([not found])
  dnl   AC_MSG_ERROR([Please reinstall the elasticapm distribution])
  dnl fi

  dnl # --with-elasticapm -> add include path
  dnl PHP_ADD_INCLUDE($ELASTICAPM_DIR/include)

  dnl # --with-elasticapm -> check for lib and symbol presence
  dnl LIBNAME=ELASTICAPM # you may want to change this
  dnl LIBSYMBOL=ELASTICAPM # you most likely want to change this

  dnl PHP_CHECK_LIBRARY($LIBNAME,$LIBSYMBOL,
  dnl [
  dnl   PHP_ADD_LIBRARY_WITH_PATH($LIBNAME, $ELASTICAPM_DIR/$PHP_LIBDIR, ELASTICAPM_SHARED_LIBADD)
  dnl   AC_DEFINE(HAVE_ELASTICAPMLIB,1,[ ])
  dnl ],[
  dnl   AC_MSG_ERROR([wrong elasticapm lib version or lib not found])
  dnl ],[
  dnl   -L$ELASTICAPM_DIR/$PHP_LIBDIR -lm
  dnl ])
  dnl
  dnl PHP_SUBST(ELASTICAPM_SHARED_LIBADD)

  dnl # In case of no dependencies
  AC_DEFINE(HAVE_ELASTICAPM, 1, [ Have elasticapm support ])

  PHP_NEW_EXTENSION(elasticapm, elasticapm.c cpu_usage.c, $ext_shared)
fi
