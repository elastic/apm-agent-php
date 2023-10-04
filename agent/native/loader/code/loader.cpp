#include "phpdetection.h"

#include <stddef.h>
#include <stdio.h>
#include <dlfcn.h>
#include <filesystem>

#include "elastic_apm_version.h"

namespace elasticapm::loader {

namespace phpdata {

#define INIT_FUNC_ARGS		int type, int module_number
#define INIT_FUNC_ARGS_PASSTHRU	type, module_number
#define SHUTDOWN_FUNC_ARGS	int type, int module_number
#define SHUTDOWN_FUNC_ARGS_PASSTHRU type, module_number
#define ZEND_MODULE_INFO_FUNC_ARGS zend_module_entry *zend_module
#define ZEND_MODULE_INFO_FUNC_ARGS_PASSTHRU zend_module

struct zend_module_entry {
    unsigned short size;
    unsigned int zend_api;
    unsigned char zend_debug;
    unsigned char zts;
    const void *ini_entry;
    const void *deps;
    const char *name;
    const void *functions;
    int (*module_startup_func)(INIT_FUNC_ARGS);
    int (*module_shutdown_func)(SHUTDOWN_FUNC_ARGS);
    int (*request_startup_func)(INIT_FUNC_ARGS);
    int (*request_shutdown_func)(SHUTDOWN_FUNC_ARGS);
    void (*info_func)(ZEND_MODULE_INFO_FUNC_ARGS);
    const char *version;
    size_t globals_size;
#ifdef ZTS
    ts_rsrc_id* globals_id_ptr;
#else
    void* globals_ptr;
#endif
    void (*globals_ctor)(void *global);
    void (*globals_dtor)(void *global);
    int (*post_deactivate_func)(void);
    int module_started;
    unsigned char type;
    void *handle;
    int module_number;
    const char *build_id;
};

}

}

elasticapm::loader::phpdata::zend_module_entry elastic_apm_loader_module_entry = {
    sizeof(elasticapm::loader::phpdata::zend_module_entry),
    0, // API, f.ex.20220829
    0, // DEBUG
    0, // USING_ZTS
    nullptr,
    nullptr,
    "elastic_apm_loader",           /* Extension name */
    nullptr,                        /* zend_function_entry */
    nullptr,                        /* PHP_MINIT - Module initialization */
    nullptr,                        /* PHP_MSHUTDOWN - Module shutdown */
    nullptr,                        /* PHP_RINIT - Request initialization */
    nullptr,                        /* PHP_RSHUTDOWN - Request shutdown */
    nullptr,                        /* PHP_MINFO - Module info */
    PHP_ELASTIC_APM_VERSION,        /* Version */
    0,                              /* globals_size */
    nullptr,                        /* globals ptr */
    nullptr,                        /* PHP_GINIT */
    nullptr,                        /* PHP_GSHUTDOWN */
    nullptr,                        /* post deactivate */
     0,
    0,
    nullptr,
    0,
    "" // API20220829,NTS ..
};

extern "C" {

__attribute__ ((visibility("default"))) elasticapm::loader::phpdata::zend_module_entry *get_module(void) {
    using namespace std::string_view_literals;
    using namespace std::string_literals;

    auto zendVersion = elasticapm::loader::getMajorMinorZendVersion();
    if (zendVersion.empty()) {
        fprintf(stderr, "Can't find Zend/PHP Engine version\n");
        return &elastic_apm_loader_module_entry;
    }

    auto [zendEngineVersion, zendModuleApiVersion, isVersionSupported] = elasticapm::loader::getZendModuleApiVersion(zendVersion);

    bool isThreadSafe = elasticapm::loader::isThreadSafe();

    static std::string zendBuildId{"API"s};
    zendBuildId.append(std::to_string(zendModuleApiVersion));
    zendBuildId.append(isThreadSafe ? ",TS"sv : ",NTS"sv);

    elastic_apm_loader_module_entry.zend_api = zendModuleApiVersion;
    elastic_apm_loader_module_entry.build_id = zendBuildId.c_str();
    elastic_apm_loader_module_entry.zts = isThreadSafe;

    if (!isVersionSupported) {
        fprintf(stderr, "Zend Engine version %s is not supported by Elastic APM Agent\n", std::string(zendVersion).c_str());
        return &elastic_apm_loader_module_entry;
    }

    if (isThreadSafe) {
        fprintf(stderr, "Thread Safe mode (ZTS) is not supported by Elastic APM Agent\n");
        return &elastic_apm_loader_module_entry; // unsupported thread safe mode
    }

    // get path to libraries
    Dl_info dl_info;
    dladdr((void *)get_module, &dl_info);
    if (!dl_info.dli_fname) {
        fprintf(stderr, "Unable to resolve path to Elastic PHP Agent libraries\n");
        return &elastic_apm_loader_module_entry;
    }

    auto elasticAgentPath = std::filesystem::path(dl_info.dli_fname).parent_path();

    auto agentLibrary = (elasticAgentPath/"elastic_apm-"sv);
    agentLibrary += std::to_string(zendModuleApiVersion);
    agentLibrary += ".so"sv;

    void *agentHandle = dlopen(agentLibrary.c_str(), RTLD_LAZY | RTLD_GLOBAL);
    if (!agentHandle) {
        fprintf(stderr, "Unable to load agent library from path: %s\n", agentLibrary.c_str());
        return &elastic_apm_loader_module_entry;
    }

    auto agentGetModule = reinterpret_cast<elasticapm::loader::phpdata::zend_module_entry *(*)(void)>(dlsym(agentHandle, "get_module"));
    if (!agentGetModule) {
        fprintf(stderr, "Unable to resolve agent entry point from library: %s\n", agentLibrary.c_str());
        return &elastic_apm_loader_module_entry;
    }

    return agentGetModule(); // or we can call zend_register_module_ex(agentGetModule())) and have both fully loaded
}


}