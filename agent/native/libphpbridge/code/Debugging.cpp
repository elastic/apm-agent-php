#include "PhpBridge.h"

#include <php.h>
#include <ext/standard/info.h>
#include <main/SAPI.h>
#include <Zend/zend_modules.h>
#include <Zend/zend_extensions.h>

namespace elasticapm::php {

using namespace std::string_view_literals;

static int getModuleData(zval *item, void *arg) {
    zend_module_entry *module = (zend_module_entry *)Z_PTR_P(item);

    PhpBridgeInterface::phpExtensionInfo_t info;
    if (module->name) {
        info.name = module->name;
    }
    if (module->version) {
        info.version = module->version;
    }

    ((std::vector<PhpBridgeInterface::phpExtensionInfo_t> *)arg)->emplace_back(std::move(info));
    return 0;
}

static void getExtensionData(zend_extension *item, void *arg) {
    PhpBridgeInterface::phpExtensionInfo_t info;
    if (item->name) {
        info.name = item->name;
    }
    if (item->version) {
        info.version = item->version;
    }

    static_cast<std::vector<PhpBridgeInterface::phpExtensionInfo_t> *>(arg)->emplace_back(std::move(info));
}

std::vector<PhpBridge::phpExtensionInfo_t> PhpBridge::getExtensionList() const {
    std::vector<phpExtensionInfo_t> extensions;
	zend_hash_apply_with_argument(&module_registry, getModuleData, &extensions);
    zend_llist_apply_with_argument(&zend_extensions, (llist_apply_with_arg_func_t)getExtensionData, &extensions);
    return extensions;
}


static std::string *phpInfoTempBuffer = nullptr;

static void phpInfoToStringOutputHandler(char *output, size_t output_len, char **handled_output, size_t *handled_output_len, int mode) {
    if (output && phpInfoTempBuffer) {
        phpInfoTempBuffer->append(output, output_len);
    }
    *handled_output = nullptr;
    *handled_output_len = 0;
}

// be aware that this function can be only called in request scope and can contain request and process metadata and secrets
// function is not thread safe - use static global buffer pointer
std::string PhpBridge::getPhpInfo() const {
    if (php_output_start_internal(ZEND_STRL("elastic_phpinfo_dump"), phpInfoToStringOutputHandler, 0, PHP_OUTPUT_HANDLER_STDFLAGS) != ZEND_RESULT_CODE::SUCCESS) {
        return {};
    }

    std::string output;
    phpInfoTempBuffer = &output;
    
    auto orig_php_info_as_text = sapi_module.phpinfo_as_text;
    sapi_module.phpinfo_as_text = 1;

    php_print_info(PHP_INFO_ALL & ~(PHP_INFO_CREDITS | PHP_INFO_LICENSE));

    sapi_module.phpinfo_as_text = orig_php_info_as_text;

	php_output_discard();
    phpInfoTempBuffer = nullptr;

    return output;
}

}
