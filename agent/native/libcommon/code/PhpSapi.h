#pragma once

#include <string>


namespace elasticapm::php {


class PhpSapi {
public:
    enum class Type : uint8_t {
        Apache,
        FPM,
        CLI,
        CLI_SERVER,
        CGI,
        CGI_FCGI,
        LITESPEED,
        PHPDBG,
        EMBED,
        FUZZER,
        UWSGI,
        FRANKENPHP,
        UNKNOWN,
    };

    PhpSapi(std::string_view sapiName) : name_{sapiName}, type_{parseSapi(sapiName)} {
    }

    bool isSupported() const {
        return type_ != Type::UNKNOWN && type_ != Type::PHPDBG && type_ != Type::EMBED;
    }

    std::string_view getName() const {
        return name_;
    }

    Type getType() const {
        return type_;
    }

private:
    Type parseSapi(std::string_view sapiName) {
        if (sapiName == "cli") {
            return Type::CLI;
        } else if (sapiName == "cli-server") {
            return Type::CLI_SERVER;
        } else if (sapiName == "cgi") {
            return Type::CGI;
        } else if (sapiName == "cgi-fcgi") {
            return Type::CGI_FCGI;
        } else if (sapiName == "fpm-fcgi") {
            return Type::FPM;
        } else if (sapiName == "apache2handler") {
            return Type::Apache;
        } else if (sapiName == "litespeed") {
            return Type::LITESPEED;
        } else if (sapiName == "phpdbg") {
            return Type::PHPDBG;
        } else if (sapiName == "embed") {
            return Type::EMBED;
        } else if (sapiName == "fuzzer") {
            return Type::FUZZER;
        } else if (sapiName == "uwsgi") {
            return Type::UWSGI;
        } else if (sapiName == "frankenphp") {
            return Type::FRANKENPHP;
        } else {
            return Type::UNKNOWN;
        }
    }

    std::string name_;
    Type type_;
};

}


