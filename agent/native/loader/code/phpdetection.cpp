#include "phpdetection.h"
#include <dlfcn.h>


namespace elasticapm::loader {

std::string_view getMajorMinorVersion(std::string_view version) {
    auto lastDot = version.find_last_of('.');
    if (lastDot == std::string_view::npos) {
        return version;
    }
    return version.substr(0, lastDot);
}

std::string_view getZendVersionString() {
    using get_zend_version_t = char *(*)(void);
    get_zend_version_t get_zend_version = reinterpret_cast<get_zend_version_t>(dlsym(RTLD_DEFAULT, "get_zend_version"));

    if (!get_zend_version) {
        return {};
    }

    const char *zendVersion = get_zend_version();
    if (!zendVersion) {
        return {};
    }

    return zendVersion;
}

std::string_view getZendVersion(std::string_view zendVersion) {
    using namespace std::string_view_literals;
    static constexpr std::string_view prefix = "Zend Engine v"sv;

    if (!zendVersion.starts_with(prefix)) {
        return {};
    }
    std::string_view version = zendVersion.substr(prefix.length(), zendVersion.find_first_of(',') - prefix.length());
    return version;
}


std::string_view getMajorMinorZendVersion() {
    auto zendVersion = getZendVersion(getZendVersionString());
    if (zendVersion.empty()) {
        return {};
    }

    return getMajorMinorVersion(zendVersion);
}

bool isThreadSafe() {
    void *coreGlobals = dlsym(RTLD_DEFAULT, "core_globals");
    return !coreGlobals;
}

std::tuple<std::string_view, int, bool> getZendModuleApiVersion(std::string_view zendVersion) {
    using namespace std::string_view_literals;
    constexpr size_t knownVersionsCount = 18;

    constexpr std::array<std::tuple<std::string_view, int, bool>, knownVersionsCount> knownPhpVersions {{
        {"4.5"sv, 20250925, true},    // PHP 8.5
        {"4.4"sv, 20240924, true},    // PHP 8.4
        {"4.3"sv, 20230831, true},    // PHP 8.3
        {"4.2"sv, 20220829, true},    // PHP 8.2
        {"4.1"sv, 20210902, true},    // PHP 8.1
        {"4.0"sv, 20200930, true},    // PHP 8.0
        {"3.4"sv, 20190902, true},    // PHP 7.4
        {"3.3"sv, 20180731, true},    // PHP 7.3
        {"3.2"sv, 20170718, true},    // PHP 7.2
        {"3.1"sv, 20160303, false},   // PHP 7.1
        {"3.0"sv, 20151012, false},   // PHP 7.0
        {"2.6"sv, 20131226, false},   // PHP 5.6
        {"2.5"sv, 20121212, false},   // PHP 5.5
        {"2.4"sv, 20100525, false},   // PHP 5.4
        {"2.3"sv, 20090626, false},   // PHP 5.3
        {"2.2"sv, 20060613, false},   // PHP 5.2
        {"2.1"sv, 20050922, false},   // PHP 5.1
        {"2.0"sv, 20041030, false}    // PHP 5.0
        }};

    auto foundPhpVersion = std::find_if(std::begin(knownPhpVersions), std::end(knownPhpVersions), [zendVersion](std::tuple<std::string_view, int, bool> const &entry) {
        return std::get<0>(entry) == zendVersion;
    });

    if (foundPhpVersion == std::end(knownPhpVersions)) {
        return {zendVersion, 0, false};
    }

    return *foundPhpVersion;
}




}
