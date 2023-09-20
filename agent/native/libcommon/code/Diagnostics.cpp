
#include "Diagnostics.h"

#include <sys/types.h>
#include <unistd.h>

#include <fstream>
#include <sstream>
#include <iomanip>

namespace elasticapm::utils {

namespace detail {
    static constexpr int separatorWidth = 60;
    static constexpr char separator = '=';
}

static void getProcessDiags(std::ostream &out, std::string_view name) {
    out << std::setfill(detail::separator) << std::setw(detail::separatorWidth) << detail::separator << std::endl;
    out << "Process " << name << ":" << std::endl;
    out << std::setfill(detail::separator) << std::setw(detail::separatorWidth) << detail::separator << std::endl;
    try {
        std::stringstream mapsname;
        mapsname << "/proc/" << getpid() << "/" << name;
        std::ifstream maps(mapsname.str());
        out << maps.rdbuf();
        maps.close();
    } catch (std::exception const &e) {
        out << "Unable to get process " << name << ": " << e.what() << std::endl; 
    }
}

static void getDiagnosticInformation(std::ostream &out, elasticapm::php::PhpBridgeInterface const &bridge) {
    out << std::setfill(detail::separator) << std::setw(detail::separatorWidth) << detail::separator << std::endl;
    out << "Elastic APM PHP agent diagnostics:" << std::endl;
    out << std::setfill(detail::separator) << std::setw(detail::separatorWidth) << detail::separator << std::endl;
    out << "PID: " << getpid() << std::endl;
    out << "PPID: " << getppid() << std::endl;
    out << "UID: " << getuid() << std::endl;

    auto extensions = bridge.getExtensionList();
    if (extensions.size() > 0) {
        out << std::setfill(detail::separator) << std::setw(detail::separatorWidth) << detail::separator << std::endl;
        out << "Loaded extensions:" << std::endl;
        out << std::setfill(detail::separator) << std::setw(detail::separatorWidth) << detail::separator << std::endl;

        for (auto const &extension : extensions) {
            out << extension.name << " " << extension.version << std::endl;
        }
    }

    out << std::setfill(detail::separator) << std::setw(detail::separatorWidth) << detail::separator << std::endl << std::endl;
    out << "phpinfo() output:" << std::endl;
    out << std::setfill(detail::separator) << std::setw(detail::separatorWidth) << detail::separator << std::endl;
    out << bridge.getPhpInfo() << std::endl;

    getProcessDiags(out, "maps");
    getProcessDiags(out, "smaps_rollup");
    getProcessDiags(out, "status");
}

void storeDiagnosticInformation(std::string_view outputFileName, elasticapm::php::PhpBridgeInterface const &bridge) {
    std::ofstream out;
    out.exceptions(std::ios_base::failbit);
    out.open(outputFileName.data());
    getDiagnosticInformation(out, bridge);
}

}
