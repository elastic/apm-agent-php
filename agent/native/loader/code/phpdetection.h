#pragma once

#include <algorithm>
#include <array>
#include <tuple>
#include <string>
#include <string_view>

namespace elasticapm::loader {

std::string_view getMajorMinorZendVersion();
std::tuple<std::string_view, int, bool> getZendModuleApiVersion(std::string_view zendVersion);
bool isThreadSafe();

}