import os
import shutil

from conans import tools, ConanFile, AutoToolsBuildEnvironment

class PhpHeadersForPHP81Conan(ConanFile):
    description = "PHP headers package required to build Elastic APM agent without additional PHP dependencies"
    license = "The PHP License, version 3.01"
    homepage = "https://php.net/"
    url = "https://php.net/"
    author = "pawel.filipczak@elastic.co"

    settings = "os", "compiler", "build_type", "arch"
    platform = "linux"

    def init(self):
        self.name = self.conan_data["name"]
        self.version = self.conan_data["version"] # version of the package
        self.php_version = self.conan_data["php_source_version"] # version of the PHP to build
        self.source_temp_dir = "php-src"

    def requirements(self):
        self.requires("libxml2/2.9.9")
        self.requires("sqlite3/3.29.0")

    def source(self):
        for source in self.conan_data["sources"][self.php_version][self.platform]:

            if "contentsRoot" in source:
                # small hack - it can't contain custom fields, so we're removing it from source (got an unexpected keyword argument)
                contentRoot = source["contentsRoot"]
                del source["contentsRoot"]
                tools.get(**source)
                os.rename(contentRoot, self.source_temp_dir)
            else:
                self.output.error("Could not find 'contentsRoot' in conandata.yml")
                raise Exception("Could not find 'contentsRoot' in conandata.yml")

    def build(self):
        with tools.chdir(os.path.join(self.source_folder, self.source_temp_dir)):
            buildEnv = AutoToolsBuildEnvironment(self)
            envVariables = buildEnv.vars
            envVariables['ac_cv_php_xml2_config_path'] = os.path.join(self.deps_cpp_info["libxml2"].rootpath, "bin/xml2-config")
            envVariables['LIBXML_LIBS'] = os.path.join(self.deps_cpp_info["libxml2"].rootpath, self.deps_cpp_info["libxml2"].libdirs[0])
            envVariables['LIBXML_CFLAGS'] = "-I{}".format(os.path.join(self.deps_cpp_info["libxml2"].rootpath, self.deps_cpp_info["libxml2"].includedirs[0]))
            envVariables['SQLITE_LIBS'] = os.path.join(self.deps_cpp_info["sqlite3"].rootpath, self.deps_cpp_info["sqlite3"].libdirs[0])
            envVariables['SQLITE_CFLAGS'] = "-I{}".format(os.path.join(self.deps_cpp_info["sqlite3"].rootpath, self.deps_cpp_info["sqlite3"].includedirs[0]))
            self.run("./buildconf --force")
            buildEnv.configure(args=[""], vars=envVariables, build=False, host=False)

    def package(self):
        source = os.path.join(self.source_folder, self.source_temp_dir)
        self.copy("*.h", src=source, dst='include', keep_path=True)

    def package_id(self):
        del self.info.settings.compiler.version
