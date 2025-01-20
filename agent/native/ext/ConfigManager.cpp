/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

#include "ConfigManager.h"
#include "ConfigSnapshot.h"
#ifdef ELASTIC_APM_MOCK_STDLIB
#   include "mock_stdlib.h"
#else
#   include <stdlib.h>
#endif
#ifndef ELASTIC_APM_MOCK_PHP_DEPS
#   include <zend_ini.h>
#endif
#include "elastic_apm_assert.h"
#include "log.h"
#include "util.h"
#include "TextOutputStream.h"
#include "elastic_apm_alloc.h"
#include "time_util.h"

#define ELASTIC_APM_CURRENT_LOG_CATEGORY ELASTIC_APM_LOG_CATEGORY_CONFIG


enum ParsedOptionValueType
{
    parsedOptionValueType_undefined = 0,

    parsedOptionValueType_bool,
    parsedOptionValueType_optionalBool,
    parsedOptionValueType_string,
    parsedOptionValueType_int,
    parsedOptionValueType_duration,
    parsedOptionValueType_size,

    end_parsedOptionValueType
};
typedef enum ParsedOptionValueType ParsedOptionValueType;

#define ELASTIC_APM_ASSERT_VALID_PARSED_OPTION_VALUE_TYPE( valueType ) \
    ELASTIC_APM_ASSERT_IN_END_EXCLUDED_RANGE_UINT64( parsedOptionValueType_undefined + 1, valueType, end_parsedOptionValueType )
/**/

struct ParsedOptionValue
{

    ParsedOptionValue() { // TODO = delete 
    }

    ParsedOptionValue(bool value) : type{parsedOptionValueType_bool}, u{value} {
    }
    ParsedOptionValue(OptionalBool value) : type{parsedOptionValueType_optionalBool}, u{value} {
    }
    ParsedOptionValue(String value) : type{parsedOptionValueType_string}, u{value} {
    }
    ParsedOptionValue(int value) : type{parsedOptionValueType_int}, u{value} {
    }
    ParsedOptionValue(Duration value) : type{parsedOptionValueType_duration}, u{value} {
    }
    ParsedOptionValue(Size value) : type{parsedOptionValueType_size}, u{value} {
    }

    ParsedOptionValueType type = ParsedOptionValueType::parsedOptionValueType_undefined;
    union u
    {
        u() : boolValue(false) {} // TODO = delete;
        u(bool value) : boolValue(value) {}
        u(OptionalBool value) : optionalBoolValue(value) {}
        u(String value) : stringValue(value) {}
        u(int value) : intValue(value) {}
        u(Duration value) : durationValue(value) {}
        u(Size value) : sizeValue(value) {}

        bool boolValue;
        OptionalBool optionalBoolValue;
        String stringValue;
        int intValue;
        Duration durationValue;
        Size sizeValue;
    } u;
};
typedef struct ParsedOptionValue ParsedOptionValue;

#define ELASTIC_APM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedOptionValue ) \
    ELASTIC_APM_ASSERT_VALID_PARSED_OPTION_VALUE_TYPE( (parsedOptionValue).type )

struct EnumOptionAdditionalMetadata
{
    String* names = nullptr;
    size_t enumElementsCount = 0;
    bool isUniquePrefixEnough = false;
};
typedef struct EnumOptionAdditionalMetadata EnumOptionAdditionalMetadata;

struct DurationOptionAdditionalMetadata
{
    DurationUnits defaultUnits = durationUnits_millisecond;
    bool isNegativeValid = false;
};
typedef struct DurationOptionAdditionalMetadata DurationOptionAdditionalMetadata;

struct SizeOptionAdditionalMetadata
{
    SizeUnits defaultUnits = sizeUnits_byte;
};
typedef struct SizeOptionAdditionalMetadata SizeOptionAdditionalMetadata;

union OptionAdditionalMetadata
{
    EnumOptionAdditionalMetadata enumData;
    DurationOptionAdditionalMetadata durationData;
    SizeOptionAdditionalMetadata sizeData;
};
typedef union OptionAdditionalMetadata OptionAdditionalMetadata;

struct OptionMetadata;
typedef struct OptionMetadata OptionMetadata;
typedef String (* InterpretIniRawValueFunc )( String rawValue );
typedef ResultCode (* ParseRawValueFunc )( const OptionMetadata* optMeta, String rawValue, /* out */ ParsedOptionValue* parsedValue );
typedef String (* StreamParsedValueFunc )( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, TextOutputStream* txtOutStream );
typedef void (* SetConfigSnapshotFieldFunc )( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, ConfigSnapshot* dst );
typedef ParsedOptionValue (* GetConfigSnapshotFieldFunc )( const OptionMetadata* optMeta, const ConfigSnapshot* src );
typedef void (* ParsedOptionValueToZvalFunc )( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, zval* return_value );
struct OptionMetadata
{
    String name = nullptr;
    StringView iniName = {nullptr, 0};
    bool isSecret = false;
    bool isDynamic = false;
    bool isLoggingRelated = false;
    ParsedOptionValue defaultValue;
    InterpretIniRawValueFunc interpretIniRawValue = nullptr;
    ParseRawValueFunc parseRawValue = nullptr;
    StreamParsedValueFunc streamParsedValue = nullptr;
    SetConfigSnapshotFieldFunc setField = nullptr;
    GetConfigSnapshotFieldFunc getField = nullptr;
    ParsedOptionValueToZvalFunc parsedValueToZval = nullptr;
    OptionAdditionalMetadata additionalData = {};
};

struct RawConfigSnapshot
{
    String original[ numberOfOptions ];
    String interpreted[ numberOfOptions ];
};
typedef struct RawConfigSnapshot RawConfigSnapshot;

struct RawConfigSnapshotSource;
typedef struct RawConfigSnapshotSource RawConfigSnapshotSource;
typedef ResultCode (* GetRawOptionValueFunc )(
        const ConfigManager* configManager,
        OptionId optId,
        String* originalRawValue,
        String* interpretedRawValue );
struct RawConfigSnapshotSource
{
    String description = nullptr;
    GetRawOptionValueFunc getOptionValue = nullptr;
};

struct CombinedRawConfigSnapshot
{
    String original[ numberOfOptions ];
    String interpreted[ numberOfOptions ];
    String sourceDescriptions[ numberOfOptions ];
};
typedef struct CombinedRawConfigSnapshot CombinedRawConfigSnapshot;

struct ConfigRawData
{
    RawConfigSnapshot fromSources[ numberOfRawConfigSources ];
    CombinedRawConfigSnapshot combined;
};
typedef struct ConfigRawData ConfigRawData;

struct ConfigMetadata
{
    OptionMetadata optionsMeta[ numberOfOptions ];
    String envVarNames[ numberOfOptions ] = { nullptr };
    RawConfigSnapshotSource rawCfgSources[ numberOfRawConfigSources ];
};
typedef struct ConfigMetadata ConfigMetadata;

struct ConfigManagerCurrentState
{
    ConfigRawData* rawData = nullptr;
    ConfigSnapshot snapshot = {};
};
typedef struct ConfigManagerCurrentState ConfigManagerCurrentState;

struct ConfigManager
{
    bool isLoggingRelatedOnly = false;
    ConfigMetadata meta = {};
    ConfigManagerCurrentState current = {};
};

#define ELASTIC_APM_ASSERT_VALID_OPTION_ID( optId ) \
    ELASTIC_APM_ASSERT_IN_END_EXCLUDED_RANGE_UINT64( 0, optId, numberOfOptions )

String interpretStringIniRawValue( String rawValue )
{
    return rawValue;
}

String interpretBoolIniRawValue( String rawValue )
{
    // When PHP engine parses php.ini it automatically converts "true", "on" and "yes" to "1" (meaning true)
    // and "false", "off", "no" and "none" to "" (empty string, meaning false)
    // https://www.php.net/manual/en/function.parse-ini-file.php
    if ( rawValue != NULL && isEmtpyString( rawValue ) ) return "false";

    return rawValue;
}

String interpretOptionalBoolIniRawValue( String rawValue )
{
    return interpretBoolIniRawValue( rawValue );
}

String interpretEmptyIniRawValueAsOff( String rawValue )
{
    // When PHP engine parses php.ini it automatically converts "true", "on" and "yes" to "1" (meaning true)
    // and "false", "off", "no" and "none" to "" (empty string, meaning false)
    // https://www.php.net/manual/en/function.parse-ini-file.php
    if ( rawValue != NULL && isEmtpyString( rawValue ) ) return "off";

    return rawValue;
}

static ResultCode parseStringValue( const OptionMetadata* optMeta, String rawValue, /* out */ ParsedOptionValue* parsedValue )
{
    ELASTIC_APM_ASSERT_VALID_PTR( optMeta );
    ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_string );
    ELASTIC_APM_ASSERT_VALID_PTR( rawValue );
    ELASTIC_APM_ASSERT_VALID_PTR( parsedValue );
    ELASTIC_APM_ASSERT_EQ_UINT64( parsedValue->type, parsedOptionValueType_undefined );
    ELASTIC_APM_ASSERT_PTR_IS_NULL( parsedValue->u.stringValue );

    parsedValue->u.stringValue = rawValue;
    parsedValue->type = optMeta->defaultValue.type;
    return resultSuccess;
}

static String streamParsedString( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR( optMeta );
    ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_string );
    ELASTIC_APM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue );
    ELASTIC_APM_ASSERT_EQ_UINT64( parsedValue.type, optMeta->defaultValue.type );

    return streamUserString( parsedValue.u.stringValue, txtOutStream );
}

static void parsedStringValueToZval( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, zval* return_value )
{
    ELASTIC_APM_ASSERT_VALID_PTR( optMeta );
    ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_string );
    ELASTIC_APM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue );
    ELASTIC_APM_ASSERT_EQ_UINT64( parsedValue.type, optMeta->defaultValue.type );
    ELASTIC_APM_ASSERT_VALID_PTR( return_value );

    if ( parsedValue.u.stringValue == NULL ) RETURN_NULL();
    RETURN_STRING( parsedValue.u.stringValue );
}

static ResultCode parseBoolValueImpl( const OptionMetadata* optMeta, ParsedOptionValueType expectedType, String rawValue, /* out */ ParsedOptionValue* parsedValue )
{
    ELASTIC_APM_ASSERT_VALID_PTR( optMeta );
    ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, expectedType );
    ELASTIC_APM_ASSERT_VALID_PTR( rawValue );
    ELASTIC_APM_ASSERT_VALID_PTR( parsedValue );
    ELASTIC_APM_ASSERT_EQ_UINT64( parsedValue->type, parsedOptionValueType_undefined );

    enum { valuesCount = 4 };
    String trueValues[ valuesCount ] = { "true", "1", "yes", "on" };
    String falseValues[ valuesCount ] = { "false", "0", "no", "off" };
    ELASTIC_APM_FOR_EACH_INDEX( i, valuesCount )
    {
        if ( areStringsEqualIgnoringCase( rawValue, trueValues[ i ] ) )
        {
            parsedValue->u.boolValue = true;
            parsedValue->type = parsedOptionValueType_bool;
            return resultSuccess;
        }
        if ( areStringsEqualIgnoringCase( rawValue, falseValues[ i ] ) )
        {
            parsedValue->u.boolValue = false;
            parsedValue->type = parsedOptionValueType_bool;
            return resultSuccess;
        }
    }

    return resultFailure;
}

static ResultCode parseBoolValue( const OptionMetadata* optMeta, String rawValue, /* out */ ParsedOptionValue* parsedValue )
{
    return parseBoolValueImpl( optMeta, parsedOptionValueType_bool, rawValue, /* out */ parsedValue );
}

static String streamParsedBool( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR( optMeta );
    ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_bool );
    ELASTIC_APM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue );
    ELASTIC_APM_ASSERT_EQ_UINT64( parsedValue.type, optMeta->defaultValue.type );

    return streamBool( parsedValue.u.boolValue, txtOutStream );
}

static void parsedBoolValueToZval( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, zval* return_value )
{
    ELASTIC_APM_ASSERT_VALID_PTR( optMeta );
    ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_bool );
    ELASTIC_APM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue );
    ELASTIC_APM_ASSERT_EQ_UINT64( parsedValue.type, optMeta->defaultValue.type );
    ELASTIC_APM_ASSERT_VALID_PTR( return_value );

    RETURN_BOOL( parsedValue.u.boolValue );
}

static ResultCode parseOptionalBoolValue( const OptionMetadata* optMeta, String rawValue, /* out */ ParsedOptionValue* parsedValue )
{
    ParsedOptionValue tempParsedValue;
    ResultCode resultCode = parseBoolValueImpl( optMeta, parsedOptionValueType_optionalBool, rawValue, /* out */ &tempParsedValue );
    if ( resultCode == resultSuccess )
    {
        parsedValue->u.optionalBoolValue = makeSetOptionalBool( tempParsedValue.u.boolValue );
        parsedValue->type = parsedOptionValueType_optionalBool;
    }
    return resultCode;
}

static String streamParsedOptionalBool( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR( optMeta );
    ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_optionalBool );
    ELASTIC_APM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue );
    ELASTIC_APM_ASSERT_EQ_UINT64( parsedValue.type, optMeta->defaultValue.type );

    return streamString( optionalBoolToString( parsedValue.u.optionalBoolValue ), txtOutStream );
}

static void parsedOptionalBoolValueToZval( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, zval* return_value )
{
    ELASTIC_APM_ASSERT_VALID_PTR( optMeta );
    ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_optionalBool );
    ELASTIC_APM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue );
    ELASTIC_APM_ASSERT_EQ_UINT64( parsedValue.type, optMeta->defaultValue.type );
    ELASTIC_APM_ASSERT_VALID_PTR( return_value );

    RETURN_STRING( optionalBoolToString( parsedValue.u.optionalBoolValue ) );
}

static ResultCode parseDurationValue( const OptionMetadata* optMeta, String rawValue, /* out */ ParsedOptionValue* parsedValue )
{
    ELASTIC_APM_ASSERT_VALID_PTR( optMeta );
    ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_duration );
    ELASTIC_APM_ASSERT_VALID_PTR( rawValue );
    ELASTIC_APM_ASSERT_VALID_PTR( parsedValue );
    ELASTIC_APM_ASSERT_EQ_UINT64( parsedValue->type, parsedOptionValueType_undefined );

    ResultCode parseResultCode = parseDuration( stringToView( rawValue )
                                                , optMeta->additionalData.durationData.defaultUnits
                                                , /* out */ &parsedValue->u.durationValue );
    if ( parseResultCode == resultSuccess )
    {
        if ( parsedValue->u.durationValue.valueInUnits < 0 && ! optMeta->additionalData.durationData.isNegativeValid )
        {
            return resultParsingFailed;
        }
        parsedValue->type = parsedOptionValueType_duration;
    }

    return parseResultCode;
}

static String streamParsedDuration( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR( optMeta );
    ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_duration );
    ELASTIC_APM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue );
    ELASTIC_APM_ASSERT_EQ_UINT64( parsedValue.type, optMeta->defaultValue.type );

    return streamDuration( parsedValue.u.durationValue, txtOutStream );
}

static void parsedDurationValueToZval( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, zval* return_value )
{
    ELASTIC_APM_ASSERT_VALID_PTR( optMeta );
    ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_duration );
    ELASTIC_APM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue );
    ELASTIC_APM_ASSERT_EQ_UINT64( parsedValue.type, optMeta->defaultValue.type );
    ELASTIC_APM_ASSERT_VALID_PTR( return_value );

    RETURN_DOUBLE( durationToMilliseconds( parsedValue.u.durationValue ) );
}

static ResultCode parseSizeValue( const OptionMetadata* optMeta, String rawValue, /* out */ ParsedOptionValue* parsedValue )
{
    ELASTIC_APM_ASSERT_VALID_PTR( optMeta );
    ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_size );
    ELASTIC_APM_ASSERT_VALID_PTR( rawValue );
    ELASTIC_APM_ASSERT_VALID_PTR( parsedValue );
    ELASTIC_APM_ASSERT_EQ_UINT64( parsedValue->type, parsedOptionValueType_undefined );

    ResultCode parseResultCode = parseSize( stringToView( rawValue )
                                                , optMeta->additionalData.sizeData.defaultUnits
                                                , /* out */ &parsedValue->u.sizeValue );
    if ( parseResultCode == resultSuccess ) parsedValue->type = parsedOptionValueType_size;
    return parseResultCode;
}

static String streamParsedSize( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR( optMeta );
    ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_size );
    ELASTIC_APM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue );
    ELASTIC_APM_ASSERT_EQ_UINT64( parsedValue.type, optMeta->defaultValue.type );

    return streamSize( parsedValue.u.sizeValue, txtOutStream );
}

static void parsedSizeValueToZval( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, zval* return_value )
{
    ELASTIC_APM_ASSERT_VALID_PTR( optMeta );
    ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_size );
    ELASTIC_APM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue );
    ELASTIC_APM_ASSERT_EQ_UINT64( parsedValue.type, optMeta->defaultValue.type );
    ELASTIC_APM_ASSERT_VALID_PTR( return_value );

    RETURN_DOUBLE( sizeToBytes( parsedValue.u.sizeValue ) );
}

static
ResultCode parseEnumValue( const OptionMetadata* optMeta, String rawValue, /* out */ ParsedOptionValue* parsedValue )
{
    ELASTIC_APM_ASSERT_VALID_PTR( optMeta );
    ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_int );
    ELASTIC_APM_ASSERT_VALID_PTR( rawValue );
    ELASTIC_APM_ASSERT_VALID_PTR( parsedValue );
    ELASTIC_APM_ASSERT_EQ_UINT64( parsedValue->type, parsedOptionValueType_undefined );

    int foundMatch = -1;
    StringView rawValueStrView = stringToView( rawValue );

    ELASTIC_APM_FOR_EACH_INDEX( i, optMeta->additionalData.enumData.enumElementsCount )
    {
        StringView currentEnumName = stringToView( optMeta->additionalData.enumData.names[ i ] );
        if ( ! isStringViewPrefixIgnoringCase( currentEnumName, rawValueStrView ) ) continue;

        // If match is exact (i.e., not just prefix) then we return immediately
        if ( currentEnumName.length == rawValueStrView.length )
        {
            foundMatch = (int)i;
            break;
        }

        if ( optMeta->additionalData.enumData.isUniquePrefixEnough )
        {
            // If there's more than one enum name that raw value matches as a prefix
            // then it's ambiguous, and we return failure
            if ( foundMatch != -1 )
            {
                ELASTIC_APM_LOG_ERROR(
                        "Failed to parse enum configuration option - raw value matches more than one enum as a prefix."
                        " Option name: `%s'."
                        " Raw value: `%s'."
                        " At least the following enums match: `%s' and `%s'."
                        , optMeta->name
                        , rawValue
                        , optMeta->additionalData.enumData.names[ foundMatch ]
                        , optMeta->additionalData.enumData.names[ i ] );
                return resultFailure;
            }

            foundMatch = (int)i;
        }
    }

    if ( foundMatch == -1 ) return resultFailure;

    parsedValue->u.intValue = foundMatch;
    parsedValue->type = parsedOptionValueType_int;
    return resultSuccess;
}

static void parsedEnumValueToZval( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, zval* return_value )
{
    ELASTIC_APM_ASSERT_VALID_PTR( optMeta );
    ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_int );
    ELASTIC_APM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue );
    ELASTIC_APM_ASSERT_EQ_UINT64( parsedValue.type, optMeta->defaultValue.type );
    ELASTIC_APM_ASSERT_VALID_PTR( return_value );

    RETURN_LONG( (long)( parsedValue.u.intValue ) );
}

static String streamParsedLogLevel( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, TextOutputStream* txtOutStream )
{
    ELASTIC_APM_ASSERT_VALID_PTR( optMeta );
    ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_int );
    ELASTIC_APM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue );
    ELASTIC_APM_ASSERT_EQ_UINT64( parsedValue.type, optMeta->defaultValue.type );

    return streamLogLevel( (LogLevel) parsedValue.u.intValue, txtOutStream );
}

static OptionMetadata buildStringOptionMetadata(
        String name
        , StringView iniName
        , bool isSecret
        , bool isDynamic
        , String defaultValue
        , SetConfigSnapshotFieldFunc setFieldFunc
        , GetConfigSnapshotFieldFunc getFieldFunc
)
{
    return (OptionMetadata)
    {
        .name = name,
        .iniName = iniName,
        .isSecret = isSecret,
        .isDynamic = isDynamic,
        .isLoggingRelated = false,
        .defaultValue = {defaultValue},
        .interpretIniRawValue = &interpretStringIniRawValue,
        .parseRawValue = &parseStringValue,
        .streamParsedValue = &streamParsedString,
        .setField = setFieldFunc,
        .getField = getFieldFunc,
        .parsedValueToZval = &parsedStringValueToZval,
        .additionalData = {}
    };
}

static OptionMetadata buildLoggingRelatedStringOptionMetadata(
        String name
        , StringView iniName
        , bool isSecret
        , bool isDynamic
        , String defaultValue
        , SetConfigSnapshotFieldFunc setFieldFunc
        , GetConfigSnapshotFieldFunc getFieldFunc
)
{
    return (OptionMetadata)
            {
                    .name = name,
                    .iniName = iniName,
                    .isSecret = isSecret,
                    .isDynamic = isDynamic,
                    .isLoggingRelated = true,
                    .defaultValue = { defaultValue },
                    .interpretIniRawValue = &interpretStringIniRawValue,
                    .parseRawValue = &parseStringValue,
                    .streamParsedValue = &streamParsedString,
                    .setField = setFieldFunc,
                    .getField = getFieldFunc,
                    .parsedValueToZval = &parsedStringValueToZval,
                    .additionalData = {}
            };
}

static OptionMetadata buildBoolOptionMetadata(
        String name
        , StringView iniName
        , bool isSecret
        , bool isDynamic
        , bool defaultValue
        , SetConfigSnapshotFieldFunc setFieldFunc
        , GetConfigSnapshotFieldFunc getFieldFunc
)
{
    return (OptionMetadata)
    {
        .name = name,
        .iniName = iniName,
        .isSecret = isSecret,
        .isDynamic = isDynamic,
        .isLoggingRelated = false,
        .defaultValue = { defaultValue },
        .interpretIniRawValue = &interpretBoolIniRawValue,
        .parseRawValue = &parseBoolValue,
        .streamParsedValue = &streamParsedBool,
        .setField = setFieldFunc,
        .getField = getFieldFunc,
        .parsedValueToZval = &parsedBoolValueToZval,
        .additionalData = {}
    };
}

static OptionMetadata buildOptionalBoolOptionMetadata(
        String name
        , StringView iniName
        , bool isSecret
        , bool isDynamic
        , OptionalBool defaultValue
        , SetConfigSnapshotFieldFunc setFieldFunc
        , GetConfigSnapshotFieldFunc getFieldFunc
)
{
    return (OptionMetadata)
    {
        .name = name,
        .iniName = iniName,
        .isSecret = isSecret,
        .isDynamic = isDynamic,
        .isLoggingRelated = false,
        .defaultValue = { defaultValue },
        .interpretIniRawValue = &interpretOptionalBoolIniRawValue,
        .parseRawValue = &parseOptionalBoolValue,
        .streamParsedValue = &streamParsedOptionalBool,
        .setField = setFieldFunc,
        .getField = getFieldFunc,
        .parsedValueToZval = &parsedOptionalBoolValueToZval,
        .additionalData = {}
    };
}

static OptionMetadata buildDurationOptionMetadata(
        String name
        , StringView iniName
        , bool isSecret
        , bool isDynamic
        , Duration defaultValue
        , SetConfigSnapshotFieldFunc setFieldFunc
        , GetConfigSnapshotFieldFunc getFieldFunc
        , DurationUnits defaultUnits
        , bool isNegativeValid )
{
    return (OptionMetadata)
    {
        .name = name,
        .iniName = iniName,
        .isSecret = isSecret,
        .isDynamic = isDynamic,
        .isLoggingRelated = false,
        .defaultValue = { defaultValue },
        .interpretIniRawValue = &interpretStringIniRawValue,
        .parseRawValue = &parseDurationValue,
        .streamParsedValue = &streamParsedDuration,
        .setField = setFieldFunc,
        .getField = getFieldFunc,
        .parsedValueToZval = &parsedDurationValueToZval,
        .additionalData = (OptionAdditionalMetadata){ .durationData = (DurationOptionAdditionalMetadata){ .defaultUnits = defaultUnits, .isNegativeValid = isNegativeValid } }
    };
}

[[maybe_unused]] static OptionMetadata buildSizeOptionMetadata(
        String name
        , StringView iniName
        , bool isSecret
        , bool isDynamic
        , Size defaultValue
        , SetConfigSnapshotFieldFunc setFieldFunc
        , GetConfigSnapshotFieldFunc getFieldFunc
        , SizeUnits defaultUnits
)
{
    return (OptionMetadata)
    {
        .name = name,
        .iniName = iniName,
        .isSecret = isSecret,
        .isDynamic = isDynamic,
        .isLoggingRelated = false,
        .defaultValue = { defaultValue },
        .interpretIniRawValue = &interpretStringIniRawValue,
        .parseRawValue = &parseSizeValue,
        .streamParsedValue = &streamParsedSize,
        .setField = setFieldFunc,
        .getField = getFieldFunc,
        .parsedValueToZval = &parsedSizeValueToZval,
        .additionalData = (OptionAdditionalMetadata){ .sizeData = (SizeOptionAdditionalMetadata){ .defaultUnits = defaultUnits } }
    };
}

static OptionMetadata buildEnumOptionMetadata(
        String name
        , StringView iniName
        , bool isSecret
        , bool isDynamic
        , bool isLoggingRelated
        , int defaultValue
        , InterpretIniRawValueFunc interpretIniRawValue
        , SetConfigSnapshotFieldFunc setFieldFunc
        , GetConfigSnapshotFieldFunc getFieldFunc
        , StreamParsedValueFunc streamParsedValue
        , EnumOptionAdditionalMetadata additionalMetadata
)
{
    return (OptionMetadata)
    {
        .name = name,
        .iniName = iniName,
        .isSecret = isSecret,
        .isDynamic = isDynamic,
        .isLoggingRelated = isLoggingRelated,
        .defaultValue = { defaultValue },
        .interpretIniRawValue = interpretIniRawValue,
        .parseRawValue = &parseEnumValue,
        .streamParsedValue = streamParsedValue,
        .setField = setFieldFunc,
        .getField = getFieldFunc,
        .parsedValueToZval = &parsedEnumValueToZval,
        .additionalData = (OptionAdditionalMetadata){ .enumData = additionalMetadata }
    };
}

static void initOptionMetadataForId( OptionMetadata* optsMeta
                                     , OptionId actualOptId
                                     , OptionId expectedOptId
                                     , OptionMetadata optionMetadata )
{
    ELASTIC_APM_ASSERT_VALID_PTR( optsMeta );
    ELASTIC_APM_ASSERT_VALID_OPTION_ID( actualOptId );
    ELASTIC_APM_ASSERT_VALID_OPTION_ID( expectedOptId );
    ELASTIC_APM_ASSERT_EQ_UINT64( actualOptId, expectedOptId );

    ELASTIC_APM_FOR_EACH_INDEX( i, actualOptId )
        ELASTIC_APM_ASSERT( ! areStringsEqualIgnoringCase( optsMeta[ i ].name, optionMetadata.name )
        , "i: %u, optionMetadata.name: %s", (unsigned int)i, optionMetadata.name );

    optsMeta[ actualOptId ] = optionMetadata;
}

#define ELASTIC_APM_FREE_AND_RESET_FIELD_FUNC_NAME( fieldName ) freeAndReset_ConfigSnapshot_##fieldName##_field
#define ELASTIC_APM_SET_FIELD_FUNC_NAME( fieldName ) set_ConfigSnapshot_##fieldName##_field
#define ELASTIC_APM_GET_FIELD_FUNC_NAME( fieldName ) get_ConfigSnapshot_##fieldName##_field

#define ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( unionFieldForType, fieldName ) \
    static void ELASTIC_APM_SET_FIELD_FUNC_NAME( fieldName ) ( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, ConfigSnapshot* dst ) \
    { \
        ELASTIC_APM_ASSERT_VALID_PTR( optMeta ); \
        ELASTIC_APM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue ); \
        ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedValue.type ); \
        ELASTIC_APM_ASSERT_VALID_PTR( dst ); \
        \
        dst->fieldName = parsedValue.u.unionFieldForType; \
    } \
    \
    static ParsedOptionValue ELASTIC_APM_GET_FIELD_FUNC_NAME( fieldName ) ( const OptionMetadata* optMeta, const ConfigSnapshot* src ) \
    { \
        ELASTIC_APM_ASSERT_VALID_PTR( optMeta ); \
        ELASTIC_APM_ASSERT_VALID_PTR( src ); \
        \
        return ParsedOptionValue{ src->fieldName }; \
    }

#define ELASTIC_APM_DEFINE_ENUM_FIELD_ACCESS_FUNCS( EnumType, fieldName ) \
    static void ELASTIC_APM_SET_FIELD_FUNC_NAME( fieldName ) ( const OptionMetadata* optMeta, ParsedOptionValue parsedValue,  ConfigSnapshot* dst ) \
    { \
        ELASTIC_APM_ASSERT_VALID_PTR( optMeta ); \
        ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_int ); \
        ELASTIC_APM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue ); \
        ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedValue.type ); \
        ELASTIC_APM_ASSERT_VALID_PTR( dst ); \
        \
        dst->fieldName = (EnumType)( parsedValue.u.intValue ); \
    } \
    \
    static ParsedOptionValue ELASTIC_APM_GET_FIELD_FUNC_NAME( fieldName ) ( const OptionMetadata* optMeta, const ConfigSnapshot* src ) \
    { \
        ELASTIC_APM_ASSERT_VALID_PTR( optMeta ); \
        ELASTIC_APM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_int ); \
        ELASTIC_APM_ASSERT_VALID_PTR( src ); \
        \
        return ParsedOptionValue{(int)(src->fieldName)}; \
    }

ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( boolValue, abortOnMemoryLeak )
#   ifdef PHP_WIN32
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( boolValue, allowAbortDialog )
#   endif
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, apiKey )
#   if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )
ELASTIC_APM_DEFINE_ENUM_FIELD_ACCESS_FUNCS( AssertLevel, assertLevel )
#   endif
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( boolValue, astProcessEnabled )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( boolValue, astProcessDebugDumpConvertedBackToSource )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, astProcessDebugDumpForPathPrefix )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, astProcessDebugDumpOutDir )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( optionalBoolValue, asyncBackendComm )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, bootstrapPhpPartFile )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( boolValue, breakdownMetrics )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( boolValue, captureErrors )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, devInternal )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( boolValue, devInternalBackendCommLogVerbose )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, disableInstrumentations )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( boolValue, disableSend )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( boolValue, enabled )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, environment )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, globalLabels )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, hostname )
ELASTIC_APM_DEFINE_ENUM_FIELD_ACCESS_FUNCS( InternalChecksLevel, internalChecksLevel )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, logFile )
ELASTIC_APM_DEFINE_ENUM_FIELD_ACCESS_FUNCS( LogLevel, logLevel )
ELASTIC_APM_DEFINE_ENUM_FIELD_ACCESS_FUNCS( LogLevel, logLevelFile )
ELASTIC_APM_DEFINE_ENUM_FIELD_ACCESS_FUNCS( LogLevel, logLevelStderr )
#   ifndef PHP_WIN32
ELASTIC_APM_DEFINE_ENUM_FIELD_ACCESS_FUNCS( LogLevel, logLevelSyslog )
#   endif
#   ifdef PHP_WIN32
ELASTIC_APM_DEFINE_ENUM_FIELD_ACCESS_FUNCS( LogLevel, logLevelWinSysDebug )
#   endif
#   if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )
ELASTIC_APM_DEFINE_ENUM_FIELD_ACCESS_FUNCS( MemoryTrackingLevel, memoryTrackingLevel )
#   endif
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, nonKeywordStringMaxLength )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( boolValue, profilingInferredSpansEnabled )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, profilingInferredSpansMinDuration )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, profilingInferredSpansSamplingInterval )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, sanitizeFieldNames )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, secretToken )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( durationValue, serverTimeout )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, serverUrl )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, serviceName )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, serviceNodeName )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, serviceVersion )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( boolValue, spanCompressionEnabled )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, spanCompressionExactMatchMaxDuration )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, spanCompressionSameKindMaxDuration )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, spanStackTraceMinDuration )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, stackTraceLimit )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, transactionIgnoreUrls )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, transactionMaxSpans )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, transactionSampleRate )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, urlGroups )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( boolValue, verifyServerCert )
ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, debugDiagnosticsFile )


#undef ELASTIC_APM_DEFINE_FIELD_ACCESS_FUNCS
#undef ELASTIC_APM_DEFINE_ENUM_FIELD_ACCESS_FUNCS

#define ELASTIC_APM_INIT_METADATA_EX( buildFunc, fieldName, optName, isSecret, isDynamic, defaultValue, ... ) \
    initOptionMetadataForId \
    ( \
        optsMeta \
        , (OptionId)(i++) \
        , optionId_##fieldName \
        , buildFunc \
        ( \
            optName \
            , ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_CFG_CONVERT_OPT_NAME_TO_INI_NAME( optName ) ) \
            , isSecret \
            , isDynamic \
            , defaultValue \
            , ELASTIC_APM_SET_FIELD_FUNC_NAME( fieldName ) \
            , ELASTIC_APM_GET_FIELD_FUNC_NAME( fieldName ) \
            , ##__VA_ARGS__ \
        ) \
    )

#define ELASTIC_APM_INIT_METADATA( buildFunc, fieldName, optName, defaultValue ) \
    ELASTIC_APM_INIT_METADATA_EX( buildFunc, fieldName, optName, /* isSecret */ false, /* isDynamic */ false, defaultValue )

#define ELASTIC_APM_INIT_DURATION_METADATA( fieldName, optName, defaultValue, defaultUnits, isNegativeValid ) \
    ELASTIC_APM_INIT_METADATA_EX( buildDurationOptionMetadata, fieldName, optName, /* isSecret */ false, /* isDynamic */ false, defaultValue, defaultUnits, isNegativeValid )

#define ELASTIC_APM_INIT_SECRET_METADATA( buildFunc, fieldName, optName, defaultValue ) \
    ELASTIC_APM_INIT_METADATA_EX( buildFunc, fieldName, optName, /* isSecret */ true, /* isDynamic */ false, defaultValue )

#define ELASTIC_APM_INIT_DYNAMIC_METADATA( buildFunc, fieldName, optName, defaultValue ) \
    ELASTIC_APM_INIT_METADATA_EX( buildFunc, fieldName, optName, /* isSecret */ false, /* isDynamic */ true, defaultValue )

#define ELASTIC_APM_ENUM_INIT_METADATA_EX( fieldName, optName, isDynamic, isLoggingRelated, defaultValue, interpretIniRawValue, enumNamesArray, isUniquePrefixEnoughArg ) \
    initOptionMetadataForId \
    ( \
        optsMeta \
        , (OptionId)(i++) \
        , optionId_##fieldName \
        , buildEnumOptionMetadata \
        ( \
            optName \
            , ELASTIC_APM_STRING_LITERAL_TO_VIEW( ELASTIC_APM_CFG_CONVERT_OPT_NAME_TO_INI_NAME( optName ) ) \
            , /* isSecret */ false \
            , isDynamic \
            , isLoggingRelated \
            , defaultValue \
            , interpretIniRawValue \
            , ELASTIC_APM_SET_FIELD_FUNC_NAME( fieldName ) \
            , ELASTIC_APM_GET_FIELD_FUNC_NAME( fieldName ) \
            , &streamParsedLogLevel \
            , (EnumOptionAdditionalMetadata) \
            { \
                .names = (enumNamesArray), \
                .enumElementsCount = ELASTIC_APM_STATIC_ARRAY_SIZE( (enumNamesArray) ), \
                .isUniquePrefixEnough = (isUniquePrefixEnoughArg) \
            } \
        ) \
    )

#define ELASTIC_APM_ENUM_INIT_METADATA( fieldName, optName, defaultValue, interpretIniRawValue, enumNamesArray, isUniquePrefixEnoughArg ) \
    ELASTIC_APM_ENUM_INIT_METADATA_EX( fieldName, optName, /* isDynamic */ false, /* isLoggingRelated */ false, defaultValue, interpretIniRawValue, enumNamesArray, isUniquePrefixEnoughArg )

#define ELASTIC_APM_INIT_LOG_LEVEL_METADATA_EX( fieldName, optName, isDynamic ) \
    ELASTIC_APM_ENUM_INIT_METADATA_EX( fieldName, optName, isDynamic, /* isLoggingRelated */ true, logLevel_not_set, &interpretEmptyIniRawValueAsOff, logLevelNames, /* isUniquePrefixEnough: */ true )

#define ELASTIC_APM_INIT_LOG_LEVEL_METADATA( fieldName, optName ) \
    ELASTIC_APM_INIT_LOG_LEVEL_METADATA_EX( fieldName, optName, /* isDynamic: */ false )

#define ELASTIC_APM_INIT_DYNAMIC_LOG_LEVEL_METADATA( fieldName, optName ) \
    ELASTIC_APM_INIT_LOG_LEVEL_METADATA_EX( fieldName, optName, /* isDynamic: */ true )

static void initOptionsMetadata( OptionMetadata* optsMeta )
{
    ELASTIC_APM_ASSERT_VALID_PTR( optsMeta );

    size_t i = 0;

    //
    // The order of calls to ELASTIC_APM_INIT_METADATA below should be the same as in OptionId
    //

    ELASTIC_APM_INIT_METADATA(
            buildBoolOptionMetadata,
            abortOnMemoryLeak,
            ELASTIC_APM_CFG_OPT_NAME_ABORT_ON_MEMORY_LEAK,
            /* defaultValue: */ ELASTIC_APM_MEMORY_TRACKING_DEFAULT_ABORT_ON_MEMORY_LEAK );

    #ifdef PHP_WIN32
    ELASTIC_APM_INIT_METADATA(
            buildBoolOptionMetadata,
            allowAbortDialog,
            ELASTIC_APM_CFG_OPT_NAME_ALLOW_ABORT_DIALOG,
            /* defaultValue: */ false );
    #endif

    ELASTIC_APM_INIT_SECRET_METADATA(
            buildStringOptionMetadata,
            apiKey,
            ELASTIC_APM_CFG_OPT_NAME_API_KEY,
            /* defaultValue: */ NULL );

    #if ( ELASTIC_APM_ASSERT_ENABLED_01 != 0 )
    ELASTIC_APM_ENUM_INIT_METADATA(
            /* fieldName: */ assertLevel,
            /* optName: */ ELASTIC_APM_CFG_OPT_NAME_ASSERT_LEVEL,
            /* defaultValue: */ assertLevel_not_set,
            &interpretEmptyIniRawValueAsOff,
            assertLevelNames,
            /* isUniquePrefixEnough: */ true );
    #endif

    ELASTIC_APM_INIT_METADATA(
            buildBoolOptionMetadata,
            astProcessEnabled,
            ELASTIC_APM_CFG_OPT_NAME_AST_PROCESS_ENABLED,
            /* defaultValue: */ false );

    ELASTIC_APM_INIT_METADATA(
            buildBoolOptionMetadata,
            astProcessDebugDumpConvertedBackToSource,
            ELASTIC_APM_CFG_OPT_NAME_AST_PROCESS_DEBUG_DUMP_CONVERTED_BACK_TO_SOURCE,
            /* defaultValue: */ true );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            astProcessDebugDumpForPathPrefix,
            ELASTIC_APM_CFG_OPT_NAME_AST_PROCESS_DEBUG_DUMP_FOR_PATH_PREFIX,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            astProcessDebugDumpOutDir,
            ELASTIC_APM_CFG_OPT_NAME_AST_PROCESS_DEBUG_DUMP_OUT_DIR,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_METADATA(
            buildOptionalBoolOptionMetadata,
            asyncBackendComm,
            ELASTIC_APM_CFG_OPT_NAME_ASYNC_BACKEND_COMM,
            /* defaultValue: */ makeNotSetOptionalBool() );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            bootstrapPhpPartFile,
            ELASTIC_APM_CFG_OPT_NAME_BOOTSTRAP_PHP_PART_FILE,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_METADATA(
            buildBoolOptionMetadata,
            breakdownMetrics,
            ELASTIC_APM_CFG_OPT_NAME_BREAKDOWN_METRICS,
            /* defaultValue: */ true );

    ELASTIC_APM_INIT_METADATA(
            buildBoolOptionMetadata,
            captureErrors,
            ELASTIC_APM_CFG_OPT_NAME_CAPTURE_ERRORS,
            /* defaultValue: */ true );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            devInternal,
            ELASTIC_APM_CFG_OPT_NAME_DEV_INTERNAL,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_METADATA(
            buildBoolOptionMetadata,
            devInternalBackendCommLogVerbose,
            ELASTIC_APM_CFG_OPT_NAME_DEV_INTERNAL_BACKEND_COMM_LOG_VERBOSE,
            /* defaultValue: */ false );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            disableInstrumentations,
            ELASTIC_APM_CFG_OPT_NAME_DISABLE_INSTRUMENTATIONS,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_METADATA(
            buildBoolOptionMetadata,
            disableSend,
            ELASTIC_APM_CFG_OPT_NAME_DISABLE_SEND,
            /* defaultValue: */ false );

    ELASTIC_APM_INIT_METADATA(
            buildBoolOptionMetadata,
            enabled,
            ELASTIC_APM_CFG_OPT_NAME_ENABLED,
            /* defaultValue: */ true );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            environment,
            ELASTIC_APM_CFG_OPT_NAME_ENVIRONMENT,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            globalLabels,
            ELASTIC_APM_CFG_OPT_NAME_GLOBAL_LABELS,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            hostname,
            ELASTIC_APM_CFG_OPT_NAME_HOSTNAME,
            /* defaultValue: */ NULL );

    ELASTIC_APM_ENUM_INIT_METADATA(
            /* fieldName: */ internalChecksLevel,
            /* optName: */ ELASTIC_APM_CFG_OPT_NAME_INTERNAL_CHECKS_LEVEL,
            /* defaultValue: */ internalChecksLevel_not_set,
            &interpretEmptyIniRawValueAsOff,
            internalChecksLevelNames,
            /* isUniquePrefixEnough: */ true );

    ELASTIC_APM_INIT_METADATA(
            buildLoggingRelatedStringOptionMetadata,
            logFile,
            ELASTIC_APM_CFG_OPT_NAME_LOG_FILE,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_DYNAMIC_LOG_LEVEL_METADATA(
            logLevel,
            ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL );
    ELASTIC_APM_INIT_LOG_LEVEL_METADATA(
            logLevelFile,
            ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_FILE );
    ELASTIC_APM_INIT_LOG_LEVEL_METADATA(
            logLevelStderr,
            ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_STDERR );
    #ifndef PHP_WIN32
    ELASTIC_APM_INIT_LOG_LEVEL_METADATA(
            logLevelSyslog,
            ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_SYSLOG );
    #endif
    #ifdef PHP_WIN32
    ELASTIC_APM_INIT_LOG_LEVEL_METADATA(
            logLevelWinSysDebug,
            ELASTIC_APM_CFG_OPT_NAME_LOG_LEVEL_WIN_SYS_DEBUG );
    #endif

    #if ( ELASTIC_APM_MEMORY_TRACKING_ENABLED_01 != 0 )
    ELASTIC_APM_ENUM_INIT_METADATA(
            /* fieldName: */ memoryTrackingLevel,
            /* optName: */ ELASTIC_APM_CFG_OPT_NAME_MEMORY_TRACKING_LEVEL,
            /* defaultValue: */ memoryTrackingLevel_not_set,
            &interpretEmptyIniRawValueAsOff,
            memoryTrackingLevelNames,
            /* isUniquePrefixEnough: */ true );
    #endif

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            nonKeywordStringMaxLength,
            ELASTIC_APM_CFG_OPT_NAME_NON_KEYWORD_STRING_MAX_LENGTH,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_METADATA(
            buildBoolOptionMetadata,
            profilingInferredSpansEnabled,
            ELASTIC_APM_CFG_OPT_NAME_PROFILING_INFERRED_SPANS_ENABLED,
            /* defaultValue: */ false );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            profilingInferredSpansMinDuration,
            ELASTIC_APM_CFG_OPT_NAME_PROFILING_INFERRED_SPANS_MIN_DURATION,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            profilingInferredSpansSamplingInterval,
            ELASTIC_APM_CFG_OPT_NAME_PROFILING_INFERRED_SPANS_SAMPLING_INTERVAL,
            "50ms" );

    ELASTIC_APM_INIT_SECRET_METADATA(
            buildStringOptionMetadata,
            sanitizeFieldNames,
            ELASTIC_APM_CFG_OPT_NAME_SANITIZE_FIELD_NAMES,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_SECRET_METADATA(
            buildStringOptionMetadata,
            secretToken,
            ELASTIC_APM_CFG_OPT_NAME_SECRET_TOKEN,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_DURATION_METADATA(
            serverTimeout
            , ELASTIC_APM_CFG_OPT_NAME_SERVER_TIMEOUT
            , /* defaultValue */ makeDuration( 30, durationUnits_second )
            , /* defaultUnits: */ durationUnits_second
            , /* isNegativeValid */ false );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            serverUrl,
            ELASTIC_APM_CFG_OPT_NAME_SERVER_URL,
            /* defaultValue: */ "http://localhost:8200" );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            serviceName,
            ELASTIC_APM_CFG_OPT_NAME_SERVICE_NAME,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            serviceNodeName,
            ELASTIC_APM_CFG_OPT_NAME_SERVICE_NODE_NAME,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            serviceVersion,
            ELASTIC_APM_CFG_OPT_NAME_SERVICE_VERSION,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_METADATA(
            buildBoolOptionMetadata,
            spanCompressionEnabled,
            ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_ENABLED,
            /* defaultValue: */ true );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            spanCompressionExactMatchMaxDuration,
            ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_EXACT_MATCH_MAX_DURATION,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            spanCompressionSameKindMaxDuration,
            ELASTIC_APM_CFG_OPT_NAME_SPAN_COMPRESSION_SAME_KIND_MAX_DURATION,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            spanStackTraceMinDuration,
            ELASTIC_APM_CFG_OPT_NAME_SPAN_STACK_TRACE_MIN_DURATION,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            stackTraceLimit,
            ELASTIC_APM_CFG_OPT_NAME_STACK_TRACE_LIMIT,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            transactionIgnoreUrls,
            ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_IGNORE_URLS,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            transactionMaxSpans,
            ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_MAX_SPANS,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            transactionSampleRate,
            ELASTIC_APM_CFG_OPT_NAME_TRANSACTION_SAMPLE_RATE,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            urlGroups,
            ELASTIC_APM_CFG_OPT_NAME_URL_GROUPS,
            /* defaultValue: */ NULL );

    ELASTIC_APM_INIT_METADATA(
            buildBoolOptionMetadata,
            verifyServerCert,
            ELASTIC_APM_CFG_OPT_NAME_VERIFY_SERVER_CERT,
            /* defaultValue: */ true );

    ELASTIC_APM_INIT_METADATA(
            buildStringOptionMetadata,
            debugDiagnosticsFile,
            ELASTIC_APM_CFG_OPT_NAME_DEBUG_DIAGNOSTICS_FILE,
            /* defaultValue: */ nullptr );

    ELASTIC_APM_ASSERT_EQ_UINT64( i, numberOfOptions );
}

#undef ELASTIC_APM_SET_FIELD_FUNC_NAME
#undef ELASTIC_APM_GET_FIELD_FUNC_NAME
#undef ELASTIC_APM_FREE_AND_RESET_FIELD_FUNC_NAME

#undef ELASTIC_APM_INIT_METADATA_EX
#undef ELASTIC_APM_INIT_METADATA
#undef ELASTIC_APM_ENUM_INIT_METADATA
#undef ELASTIC_APM_ENUM_INIT_METADATA_EX
#undef ELASTIC_APM_INIT_LOG_LEVEL_METADATA
#undef ELASTIC_APM_INIT_LOG_LEVEL_METADATA

static
void parseCombinedRawConfigSnapshot(
        ConfigManager* cfgManager,
        const CombinedRawConfigSnapshot* combinedRawCfgSnapshot,
        ConfigSnapshot* cfgSnapshot )
{
    ELASTIC_APM_ASSERT_VALID_PTR( cfgManager );
    ELASTIC_APM_ASSERT_VALID_PTR( combinedRawCfgSnapshot );
    ELASTIC_APM_ASSERT_VALID_PTR( cfgSnapshot );

    const OptionMetadata* const optsMeta = cfgManager->meta.optionsMeta;

    ELASTIC_APM_FOR_EACH_OPTION_ID( optId )
    {
        char txtOutStreamBuf[ ELASTIC_APM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
        TextOutputStream txtOutStream = ELASTIC_APM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
        const OptionMetadata* const optMeta = &( optsMeta[ optId ] );
        const String originalRawValue = combinedRawCfgSnapshot->original[ optId ];
        const String interpretedRawValue = combinedRawCfgSnapshot->interpreted[ optId ];
        const String sourceDescription = combinedRawCfgSnapshot->sourceDescriptions[ optId ];
        ParsedOptionValue parsedOptValue;
        ELASTIC_APM_ZERO_STRUCT( &parsedOptValue );

        if ( cfgManager->isLoggingRelatedOnly && !optMeta->isLoggingRelated )
        {
            continue;
        }

        if ( interpretedRawValue == NULL )
        {
            parsedOptValue = optMeta->defaultValue;
            ELASTIC_APM_LOG_DEBUG(
                    "Configuration option `%s' is not set - using default value (%s)",
                    optMeta->name,
                    optMeta->streamParsedValue( optMeta, parsedOptValue, &txtOutStream ) );
        }
        else if ( optMeta->parseRawValue( optMeta, interpretedRawValue, &parsedOptValue ) == resultSuccess )
        {
            ELASTIC_APM_LOG_DEBUG(
                    "Successfully parsed configuration option `%s' - "
                    "parsed value: %s (raw value: `%s', interpreted as: `%s', source: %s)",
                    optMeta->name,
                    optMeta->streamParsedValue( optMeta, parsedOptValue, &txtOutStream ),
                    originalRawValue,
                    interpretedRawValue,
                    sourceDescription );
        }
        else
        {
            parsedOptValue = optMeta->defaultValue;
            ELASTIC_APM_LOG_ERROR(
                    "Failed to parse configuration option `%s' - "
                    "using default value (%s). Failed to parse raw value: `%s', interpreted as: `%s', source: %s.",
                    optMeta->name,
                    optMeta->streamParsedValue( optMeta, parsedOptValue, &txtOutStream ),
                    originalRawValue,
                    interpretedRawValue,
                    sourceDescription );
        }

        optMeta->setField( optMeta, parsedOptValue, cfgSnapshot );
    }
}

static ResultCode constructEnvVarNameForOption( String optName, String* envVarName )
{
    ELASTIC_APM_ASSERT_VALID_STRING( optName );
    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( envVarName );

    ResultCode resultCode;
    StringView envVarNamePrefix = ELASTIC_APM_STRING_LITERAL_TO_VIEW( "ELASTIC_APM_" );
    MutableString envVarNameBuffer = NULL;
    const size_t envVarNameBufferSize = envVarNamePrefix.length + strlen( optName ) + 1;

    ELASTIC_APM_PEMALLOC_STRING_IF_FAILED_GOTO( envVarNameBufferSize, envVarNameBuffer );
    strcpy( envVarNameBuffer, envVarNamePrefix.begin );
    copyStringAsUpperCase( optName, /* out */ envVarNameBuffer + envVarNamePrefix.length );

    resultCode = resultSuccess;
    *envVarName = envVarNameBuffer;

    finally:
    return resultCode;

    failure:
    ELASTIC_APM_PEFREE_STRING_SIZE_AND_SET_TO_NULL( envVarNameBufferSize, envVarNameBuffer );
    goto finally;
}

static void destructEnvVarNames( /* in,out */ String envVarNames[] )
{
    ELASTIC_APM_ASSERT_VALID_PTR( envVarNames );

    ELASTIC_APM_FOR_EACH_OPTION_ID( optId )
        ELASTIC_APM_PEFREE_STRING_AND_SET_TO_NULL( envVarNames[ optId ] );
}

static ResultCode constructEnvVarNames( OptionMetadata* optsMeta, /* out */ String envVarNames[] )
{
    ELASTIC_APM_ASSERT_VALID_PTR( optsMeta );
    ELASTIC_APM_ASSERT_VALID_PTR( envVarNames );

    ResultCode resultCode;

    ELASTIC_APM_FOR_EACH_OPTION_ID( optId )
        ELASTIC_APM_CALL_IF_FAILED_GOTO( constructEnvVarNameForOption( optsMeta[ optId ].name, &envVarNames[ optId ] ) );

    resultCode = resultSuccess;

    finally:
    return resultCode;

    failure:
    destructEnvVarNames( envVarNames );
    goto finally;
}

#ifdef ELASTIC_APM_GETENV_FUNC
// Declare to avoid warnings
char* ELASTIC_APM_MOCK_GETENV_FUNC( const char* name );
#else
#define ELASTIC_APM_GETENV_FUNC getenv
#endif

String readRawOptionValueFromEnvVars( const ConfigManager* cfgManager, OptionId optId )
{
    ELASTIC_APM_ASSERT_VALID_PTR( cfgManager );
    ELASTIC_APM_ASSERT_VALID_OPTION_ID( optId );

    return ELASTIC_APM_GETENV_FUNC( cfgManager->meta.envVarNames[ optId ] );
}

static
ResultCode getRawOptionValueFromEnvVars(
        const ConfigManager* cfgManager,
        OptionId optId,
        String* originalRawValue,
        String* interpretedRawValue )
{
    ELASTIC_APM_ASSERT_VALID_PTR( cfgManager );
    ELASTIC_APM_ASSERT_VALID_OPTION_ID( optId );
    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( originalRawValue );
    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( interpretedRawValue );

    ResultCode resultCode;
    String returnedRawValue;
    String rawValue = NULL;

    returnedRawValue = readRawOptionValueFromEnvVars( cfgManager, optId );
    if ( returnedRawValue != NULL )
    {
        StringView processedRawValue;
        processedRawValue = trimStringView( makeStringViewFromString( returnedRawValue ) );
        ELASTIC_APM_PEMALLOC_DUP_STRING_VIEW_IF_FAILED_GOTO( processedRawValue.begin, processedRawValue.length, rawValue );
    }

    resultCode = resultSuccess;
    *originalRawValue = rawValue;
    *interpretedRawValue = *originalRawValue;

    finally:
    return resultCode;

    failure:
    ELASTIC_APM_PEFREE_STRING_AND_SET_TO_NULL( rawValue );
    goto finally;
}

String readRawOptionValueFromIni(
        const ConfigManager* cfgManager,
        OptionId optId,
        bool* exists )
{
    ELASTIC_APM_ASSERT_VALID_PTR( cfgManager );
    ELASTIC_APM_ASSERT_VALID_OPTION_ID( optId );

    const OptionMetadata* const optMeta = &( cfgManager->meta.optionsMeta[ optId ] );
    zend_bool existsZendBool = 0;
    String returnedRawValue = zend_ini_string_ex(
            (char*)( optMeta->iniName.begin ),
            optMeta->iniName.length,
            /* orig: */ 0,
            &existsZendBool );
    *exists = ( existsZendBool != 0 );
    return returnedRawValue;
}

static
ResultCode getRawOptionValueFromIni(
        const ConfigManager* cfgManager,
        OptionId optId,
        String* originalRawValue,
        String* interpretedRawValue )
{
    ELASTIC_APM_ASSERT_VALID_PTR( cfgManager );
    ELASTIC_APM_ASSERT_VALID_OPTION_ID( optId );
    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( originalRawValue );
    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( interpretedRawValue );

    ResultCode resultCode;
    bool exists = 0;
    String returnedRawValue = NULL;
    String rawValue = NULL;
    const OptionMetadata* const optMeta = &( cfgManager->meta.optionsMeta[ optId ] );

    returnedRawValue = readRawOptionValueFromIni( cfgManager,optId, &exists );

    if ( exists && ( returnedRawValue != NULL ) )
    {
        StringView processedRawValue;
        processedRawValue = trimStringView( makeStringViewFromString( returnedRawValue ) );
        ELASTIC_APM_PEMALLOC_DUP_STRING_VIEW_IF_FAILED_GOTO( processedRawValue.begin, processedRawValue.length, rawValue );
    }

    resultCode = resultSuccess;
    *originalRawValue = rawValue;
    *interpretedRawValue = optMeta->interpretIniRawValue( rawValue );

    finally:
    return resultCode;

    failure:
    ELASTIC_APM_PEFREE_STRING_AND_SET_TO_NULL( rawValue );
    goto finally;
}

static void initRawConfigSources( RawConfigSnapshotSource rawCfgSources[ numberOfRawConfigSources ] )
{
    ELASTIC_APM_ASSERT_VALID_PTR( rawCfgSources );

    size_t i = 0;

    ELASTIC_APM_ASSERT_EQ_UINT64( i, rawConfigSourceId_iniFile );
    rawCfgSources[ i++ ] = (RawConfigSnapshotSource)
    {
        .description = "INI file",
        .getOptionValue = &getRawOptionValueFromIni
    };

    ELASTIC_APM_ASSERT_EQ_UINT64( i, rawConfigSourceId_envVars );
    rawCfgSources[ i++ ] = (RawConfigSnapshotSource)
    {
        .description = "Environment variables",
        .getOptionValue = &getRawOptionValueFromEnvVars
    };

    ELASTIC_APM_ASSERT_EQ_UINT64( i, numberOfRawConfigSources );
}

static
void deleteConfigRawDataAndSetToNull( /* in,out */ ConfigRawData** pRawData )
{
    ELASTIC_APM_ASSERT_VALID_PTR( pRawData );

    ConfigRawData* rawData = *pRawData;
    if ( rawData == NULL ) return;
    ELASTIC_APM_ASSERT_VALID_PTR( rawData );

    ELASTIC_APM_FOR_EACH_OPTION_ID( optId )
        ELASTIC_APM_FOR_EACH_INDEX( rawSourceIndex, numberOfRawConfigSources )
        {
            const char** pOriginalRawValue = &( rawData->fromSources[ rawSourceIndex ].original[ optId ] );
            ELASTIC_APM_PEFREE_STRING_AND_SET_TO_NULL( *pOriginalRawValue );
        }

    ELASTIC_APM_PEFREE_INSTANCE_AND_SET_TO_NULL( ConfigRawData, *pRawData );
}

static
ResultCode fetchConfigRawDataFromAllSources( const ConfigManager* cfgManager, /* out */ ConfigRawData* newRawData )
{
    ELASTIC_APM_ASSERT_VALID_PTR( cfgManager );
    ELASTIC_APM_ASSERT_VALID_PTR( newRawData );

    ResultCode resultCode;

    ELASTIC_APM_FOR_EACH_OPTION_ID( optId )
    {
        ELASTIC_APM_FOR_EACH_INDEX( rawCfgSourceIndex, numberOfRawConfigSources )
        {
            ELASTIC_APM_CALL_IF_FAILED_GOTO( cfgManager->meta.rawCfgSources[ rawCfgSourceIndex ].getOptionValue(
                    cfgManager,
                    optId,
                    &newRawData->fromSources[ rawCfgSourceIndex ].original[ optId ],
                    &newRawData->fromSources[ rawCfgSourceIndex ].interpreted[ optId ] ) );
        }
    }

    resultCode = resultSuccess;

    finally:
    return resultCode;

    failure:
    goto finally;
}

static
void combineConfigRawData( const ConfigManager* cfgManager, /* in,out */ ConfigRawData* newRawData )
{
    ELASTIC_APM_ASSERT_VALID_PTR( cfgManager );
    ELASTIC_APM_ASSERT_VALID_PTR( newRawData );

    ELASTIC_APM_FOR_EACH_OPTION_ID( optId )
    {
        ELASTIC_APM_FOR_EACH_INDEX( rawCfgSourceIndex, numberOfRawConfigSources )
        {
            if ( newRawData->fromSources[ rawCfgSourceIndex ].interpreted[ optId ] == NULL ) continue;

            newRawData->combined.original[ optId ] = newRawData->fromSources[ rawCfgSourceIndex ].original[ optId ];
            newRawData->combined.interpreted[ optId ] = newRawData->fromSources[ rawCfgSourceIndex ].interpreted[ optId ];
            newRawData->combined.sourceDescriptions[ optId ] = cfgManager->meta.rawCfgSources[ rawCfgSourceIndex ].description;
            break;
        }
    }
}

static
ResultCode fetchConfigRawData( const ConfigManager* cfgManager, /* out */ ConfigRawData** pNewRawData )
{
    ELASTIC_APM_ASSERT_VALID_PTR( cfgManager );
    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( pNewRawData );

    ResultCode resultCode;
    ConfigRawData* newRawData = NULL;

    ELASTIC_APM_PEMALLOC_INSTANCE_IF_FAILED_GOTO( ConfigRawData, newRawData );
    ELASTIC_APM_ZERO_STRUCT( newRawData );

    ELASTIC_APM_CALL_IF_FAILED_GOTO( fetchConfigRawDataFromAllSources( cfgManager, /* out */ newRawData ) );
    combineConfigRawData( cfgManager, /* in,out */ newRawData );

    resultCode = resultSuccess;
    *pNewRawData = newRawData;

    finally:
    return resultCode;

    failure:
    deleteConfigRawDataAndSetToNull( &newRawData );
    goto finally;
}

static
bool areEqualCombinedRawConfigSnapshots( const CombinedRawConfigSnapshot* snapshot1, const CombinedRawConfigSnapshot* snapshot2 )
{
    ELASTIC_APM_ASSERT_VALID_PTR( snapshot1 );
    ELASTIC_APM_ASSERT_VALID_PTR( snapshot2 );

    ELASTIC_APM_FOR_EACH_OPTION_ID( optId )
        if ( ( ! areEqualNullableStrings( snapshot1->original[ optId ], snapshot2->original[ optId ] ) ) ||
                ( ! areEqualNullableStrings( snapshot1->interpreted[ optId ], snapshot2->interpreted[ optId ] ) ) ||
                ( ! areEqualNullableStrings( snapshot1->sourceDescriptions[ optId ], snapshot2->sourceDescriptions[ optId ] ) ) )
            return false;

    return true;
}

const ConfigSnapshot* getConfigManagerCurrentSnapshot( const ConfigManager* cfgManager )
{
    ELASTIC_APM_ASSERT_VALID_PTR( cfgManager );
    return &cfgManager->current.snapshot;
}

ResultCode ensureConfigManagerHasLatestConfig( ConfigManager* cfgManager, bool* didConfigChange )
{
    ELASTIC_APM_ASSERT_VALID_PTR( cfgManager );
    ELASTIC_APM_ASSERT_VALID_PTR( didConfigChange );

    ResultCode resultCode;
    ConfigRawData* newRawData = NULL;
    ConfigSnapshot newCfgSnapshot;
    ELASTIC_APM_ZERO_STRUCT(&newCfgSnapshot);
    
    ELASTIC_APM_CALL_IF_FAILED_GOTO( fetchConfigRawData( cfgManager, &newRawData ) );

    if ( cfgManager->current.rawData != NULL &&
            areEqualCombinedRawConfigSnapshots( &cfgManager->current.rawData->combined, &newRawData->combined ) )
    {
        ELASTIC_APM_LOG_DEBUG( "Current configuration is already the latest" );
        resultCode = resultSuccess;
        *didConfigChange = false;
        goto finally;
    }

    parseCombinedRawConfigSnapshot( cfgManager, &newRawData->combined, &newCfgSnapshot );
    deleteConfigRawDataAndSetToNull( /* in,out */ &cfgManager->current.rawData );
    cfgManager->current.rawData = newRawData;
    cfgManager->current.snapshot = newCfgSnapshot;
    newRawData = NULL;

    resultCode = resultSuccess;
    *didConfigChange = true;

    finally:
    deleteConfigRawDataAndSetToNull( /* in,out */ &newRawData );
    return resultCode;

    failure:
    goto finally;
}

static
void destructConfigManagerCurrentState( /* in,out */ ConfigManagerCurrentState* cfgManagerCurrent )
{
    ELASTIC_APM_ASSERT_VALID_PTR( cfgManagerCurrent );

    deleteConfigRawDataAndSetToNull( /* in,out */ &cfgManagerCurrent->rawData );

    ELASTIC_APM_ZERO_STRUCT( cfgManagerCurrent );
}

static
void initConfigManagerCurrentState(
        const ConfigMetadata* cfgManagerMeta,
        /* out */ ConfigManagerCurrentState* cfgManagerCurrent )
{
    ELASTIC_APM_ASSERT_VALID_PTR( cfgManagerMeta );
    ELASTIC_APM_ASSERT_VALID_PTR( cfgManagerCurrent );

    ELASTIC_APM_FOR_EACH_OPTION_ID( optId )
    {
        const OptionMetadata* const optMeta = &( cfgManagerMeta->optionsMeta[ optId ] );
        optMeta->setField( optMeta, optMeta->defaultValue, &cfgManagerCurrent->snapshot );
    }
}

static
void destructConfigManagerMetadata( ConfigMetadata* cfgManagerMeta )
{
    ELASTIC_APM_ASSERT_VALID_PTR( cfgManagerMeta );

    destructEnvVarNames( /* in,out */ cfgManagerMeta->envVarNames );

    ELASTIC_APM_ZERO_STRUCT( cfgManagerMeta );
}

ResultCode constructConfigManagerMetadata( ConfigMetadata* cfgManagerMeta )
{
    ELASTIC_APM_ASSERT_VALID_PTR( cfgManagerMeta );

    ResultCode resultCode;

    initOptionsMetadata( cfgManagerMeta->optionsMeta );
    ELASTIC_APM_CALL_IF_FAILED_GOTO( constructEnvVarNames( cfgManagerMeta->optionsMeta, /* out */ cfgManagerMeta->envVarNames ) );
    initRawConfigSources( cfgManagerMeta->rawCfgSources );

    resultCode = resultSuccess;

    finally:
    return resultCode;

    failure:
    destructConfigManagerMetadata( cfgManagerMeta );
    goto finally;
}

ResultCode getConfigManagerOptionValueByName(
        const ConfigManager* cfgManager
        , String optionName
        , GetConfigManagerOptionValueByNameResult* result
)
{
    ELASTIC_APM_ASSERT_VALID_PTR( result );
    ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( &result->txtOutStream );

    const OptionMetadata* optMeta = NULL;
    ELASTIC_APM_FOR_EACH_OPTION_ID( optId )
    {
        if ( areStringsEqualIgnoringCase( cfgManager->meta.optionsMeta[ optId ].name, optionName ) )
        {
            optMeta = &( cfgManager->meta.optionsMeta[ optId ] );
            break;
        }
    }
    if ( optMeta == NULL ) return resultFailure;

    optMeta->parsedValueToZval( optMeta, optMeta->getField( optMeta, &cfgManager->current.snapshot ), &result->parsedValueAsZval );
    result->streamedParsedValue = optMeta->streamParsedValue(
            optMeta, optMeta->getField( optMeta, &( cfgManager->current.snapshot ) ), &result->txtOutStream );
    return resultSuccess;
}

void getConfigManagerOptionMetadata(
        const ConfigManager* cfgManager
        , OptionId optId
        , GetConfigManagerOptionMetadataResult* result
)
{
    ELASTIC_APM_ASSERT_VALID_PTR( cfgManager );
    ELASTIC_APM_ASSERT_VALID_PTR( result );

    const OptionMetadata* const optMeta = &( cfgManager->meta.optionsMeta[ optId ] );
    result->isSecret = optMeta->isSecret;
    result->isDynamic = optMeta->isDynamic;
    result->optName = optMeta->name;
    result->envVarName = cfgManager->meta.envVarNames[ optId ];
    result->iniName = optMeta->iniName;
}

void getConfigManagerOptionValueById(
        const ConfigManager* cfgManager
        , OptionId optId
        , GetConfigManagerOptionValueByIdResult* result
)
{
    ELASTIC_APM_ASSERT_VALID_PTR( cfgManager );
    ELASTIC_APM_ASSERT_VALID_OPTION_ID( optId );
    ELASTIC_APM_ASSERT_VALID_PTR( result );
    ELASTIC_APM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( &result->txtOutStream );

    const OptionMetadata* const optMeta = &( cfgManager->meta.optionsMeta[ optId ] );
    const ParsedOptionValue parsedOpVal = optMeta->getField( optMeta, &( cfgManager->current.snapshot ) );
    result->streamedParsedValue =
        ( parsedOpVal.type == parsedOptionValueType_string && parsedOpVal.u.stringValue == NULL )
            ? NULL
            : optMeta->streamParsedValue( optMeta, parsedOpVal, &result->txtOutStream );

    if ( cfgManager->current.rawData == NULL )
    {
        result->rawValue = NULL;
        result->rawValueSourceDescription = NULL;
    }
    else
    {
        result->rawValue = cfgManager->current.rawData->combined.original[ optId ];
        result->rawValueSourceDescription = cfgManager->current.rawData->combined.sourceDescriptions[ optId ];
    }
}

void getConfigManagerRawData(
        const ConfigManager* cfgManager,
        OptionId optId,
        RawConfigSourceId rawCfgSourceId,
        /* out */ String* originalRawValue,
        /* out */ String* interpretedRawValue )
{
    ELASTIC_APM_ASSERT_VALID_PTR( cfgManager );
    ELASTIC_APM_ASSERT_VALID_OPTION_ID( optId );
    ELASTIC_APM_ASSERT_LT_UINT64( rawCfgSourceId, numberOfRawConfigSources );
    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( originalRawValue );
    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( interpretedRawValue );

    *originalRawValue = cfgManager->current.rawData->fromSources[ rawCfgSourceId ].original[ optId ];
    *interpretedRawValue = cfgManager->current.rawData->fromSources[ rawCfgSourceId ].interpreted[ optId ];
}

void deleteConfigManagerAndSetToNull( ConfigManager** pCfgManager )
{
    ELASTIC_APM_ASSERT_VALID_PTR( pCfgManager );

    ConfigManager* const cfgManager = *pCfgManager;
    if ( cfgManager == NULL ) return;
    ELASTIC_APM_ASSERT_VALID_PTR( cfgManager );

    destructConfigManagerCurrentState( /* in,out */ &cfgManager->current );
    destructConfigManagerMetadata( /* in,out */ &cfgManager->meta );

    ELASTIC_APM_ZERO_STRUCT( cfgManager );

    ELASTIC_APM_PEFREE_INSTANCE_AND_SET_TO_NULL( ConfigManager, *pCfgManager );
}

ResultCode newConfigManager( ConfigManager** pNewCfgManager, bool isLoggingRelatedOnly )
{
    ELASTIC_APM_ASSERT_VALID_OUT_PTR_TO_PTR( pNewCfgManager );

    ResultCode resultCode;
    ConfigManager* cfgManager = NULL;

    ELASTIC_APM_PEMALLOC_INSTANCE_IF_FAILED_GOTO( ConfigManager, cfgManager );
    ELASTIC_APM_ZERO_STRUCT( cfgManager );

    cfgManager->isLoggingRelatedOnly = isLoggingRelatedOnly;
    ELASTIC_APM_CALL_IF_FAILED_GOTO( constructConfigManagerMetadata( /* out */ &cfgManager->meta ) );
    initConfigManagerCurrentState( &cfgManager->meta, /* out */ &cfgManager->current );

    resultCode = resultSuccess;
    *pNewCfgManager = cfgManager;

    finally:
    return resultCode;

    failure:
    deleteConfigManagerAndSetToNull( /* in,out */ &cfgManager );
    goto finally;
}
