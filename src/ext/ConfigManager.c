/*
   +----------------------------------------------------------------------+
   | Elastic APM agent for PHP                                            |
   +----------------------------------------------------------------------+
   | Copyright (c) 2020 Elasticsearch B.V.                                |
   +----------------------------------------------------------------------+
   | Elasticsearch B.V. licenses this file under the Apache 2.0 License.  |
   | See the LICENSE file in the project root for more information.       |
   +----------------------------------------------------------------------+
 */

#include "ConfigManager.h"
#ifdef ELASTICAPM_MOCK_STDLIB
#   include "mock_stdlib.h"
#else
#   include <stdlib.h>
#endif
#ifndef ELASTICAPM_MOCK_PHP_DEPS
#   include <zend_ini.h>
#endif
#include "elasticapm_assert.h"
#include "log.h"
#include "util.h"
#include "TextOutputStream.h"
#include "elasticapm_alloc.h"
#include "time_util.h"

#define ELASTICAPM_CURRENT_LOG_CATEGORY ELASTICAPM_LOG_CATEGORY_CONFIG

enum ParsedOptionValueType
{
    parsedOptionValueType_undefined = 0,

    parsedOptionValueType_bool,
    parsedOptionValueType_string,
    parsedOptionValueType_int,
    parsedOptionValueType_duration,

    end_parsedOptionValueType
};
typedef enum ParsedOptionValueType ParsedOptionValueType;

#define ELASTICAPM_ASSERT_VALID_PARSED_OPTION_VALUE_TYPE( valueType ) \
    ELASTICAPM_ASSERT_IN_END_EXCLUDED_RANGE_UINT64( parsedOptionValueType_undefined + 1, valueType, end_parsedOptionValueType )
/**/

struct ParsedOptionValue
{
    ParsedOptionValueType type;
    union
    {
        bool boolValue;
        String stringValue;
        int intValue;
        Duration durationValue;
    } u;
};
typedef struct ParsedOptionValue ParsedOptionValue;

#define ELASTICAPM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedOptionValue ) \
    ELASTICAPM_ASSERT_VALID_PARSED_OPTION_VALUE_TYPE( (parsedOptionValue).type )

struct EnumOptionAdditionalMetadata
{
    String* names;
    size_t enumElementsCount;
    bool isUniquePrefixEnough;
};
typedef struct EnumOptionAdditionalMetadata EnumOptionAdditionalMetadata;

struct DurationOptionAdditionalMetadata
{
    DurationUnits defaultUnits;
};
typedef struct DurationOptionAdditionalMetadata DurationOptionAdditionalMetadata;

union OptionAdditionalMetadata
{
    EnumOptionAdditionalMetadata enumData;
    DurationOptionAdditionalMetadata durationData;
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
    bool isSecret;
    String name;
    StringView iniName;
    ParsedOptionValue defaultValue;
    InterpretIniRawValueFunc interpretIniRawValue;
    ParseRawValueFunc parseRawValue;
    StreamParsedValueFunc streamParsedValue;
    SetConfigSnapshotFieldFunc setField;
    GetConfigSnapshotFieldFunc getField;
    ParsedOptionValueToZvalFunc parsedValueToZval;
    OptionAdditionalMetadata additionalData;
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
    String description;
    GetRawOptionValueFunc getOptionValue;
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
    String envVarNames[ numberOfOptions ];
    RawConfigSnapshotSource rawCfgSources[ numberOfRawConfigSources ];
};
typedef struct ConfigMetadata ConfigMetadata;

struct ConfigManagerCurrentState
{
    ConfigRawData* rawData;
    ConfigSnapshot snapshot;
};
typedef struct ConfigManagerCurrentState ConfigManagerCurrentState;

struct ConfigManager
{
    ConfigMetadata meta;
    ConfigManagerCurrentState current;
};

#define ELASTICAPM_ASSERT_VALID_OPTION_ID( optId ) \
    ELASTICAPM_ASSERT_IN_END_EXCLUDED_RANGE_UINT64( 0, optId, numberOfOptions )

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
    ELASTICAPM_ASSERT_VALID_PTR( optMeta );
    ELASTICAPM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_string );
    ELASTICAPM_ASSERT_VALID_PTR( rawValue );
    ELASTICAPM_ASSERT_VALID_PTR( parsedValue );
    ELASTICAPM_ASSERT_EQ_UINT64( parsedValue->type, parsedOptionValueType_undefined );
    ELASTICAPM_ASSERT_PTR_IS_NULL( parsedValue->u.stringValue );

    parsedValue->u.stringValue = rawValue;
    parsedValue->type = optMeta->defaultValue.type;
    return resultSuccess;
}

static String streamParsedString( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, TextOutputStream* txtOutStream )
{
    ELASTICAPM_ASSERT_VALID_PTR( optMeta );
    ELASTICAPM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_string );
    ELASTICAPM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue );
    ELASTICAPM_ASSERT_EQ_UINT64( parsedValue.type, optMeta->defaultValue.type );

    return streamUserString( parsedValue.u.stringValue, txtOutStream );
}

static void parsedStringValueToZval( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, zval* return_value )
{
    ELASTICAPM_ASSERT_VALID_PTR( optMeta );
    ELASTICAPM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_string );
    ELASTICAPM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue );
    ELASTICAPM_ASSERT_EQ_UINT64( parsedValue.type, optMeta->defaultValue.type );
    ELASTICAPM_ASSERT_VALID_PTR( return_value );

    if ( parsedValue.u.stringValue == NULL ) RETURN_NULL()
    RETURN_STRING( parsedValue.u.stringValue )
}

static ResultCode parseBoolValue( const OptionMetadata* optMeta, String rawValue, /* out */ ParsedOptionValue* parsedValue )
{
    ELASTICAPM_ASSERT_VALID_PTR( optMeta );
    ELASTICAPM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_bool );
    ELASTICAPM_ASSERT_VALID_PTR( rawValue );
    ELASTICAPM_ASSERT_VALID_PTR( parsedValue );
    ELASTICAPM_ASSERT_EQ_UINT64( parsedValue->type, parsedOptionValueType_undefined );

    enum { valuesCount = 4 };
    String trueValues[ valuesCount ] = { "true", "1", "yes", "on" };
    String falseValues[ valuesCount ] = { "false", "0", "no", "off" };
    ELASTICAPM_FOR_EACH_INDEX( i, valuesCount )
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

static String streamParsedBool( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, TextOutputStream* txtOutStream )
{
    ELASTICAPM_ASSERT_VALID_PTR( optMeta );
    ELASTICAPM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_bool );
    ELASTICAPM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue );
    ELASTICAPM_ASSERT_EQ_UINT64( parsedValue.type, optMeta->defaultValue.type );

    return streamBool( parsedValue.u.boolValue, txtOutStream );
}

static void parsedBoolValueToZval( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, zval* return_value )
{
    ELASTICAPM_ASSERT_VALID_PTR( optMeta );
    ELASTICAPM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_bool );
    ELASTICAPM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue );
    ELASTICAPM_ASSERT_EQ_UINT64( parsedValue.type, optMeta->defaultValue.type );
    ELASTICAPM_ASSERT_VALID_PTR( return_value );

    RETURN_BOOL( parsedValue.u.boolValue )
}

static ResultCode parseDurationValue( const OptionMetadata* optMeta, String rawValue, /* out */ ParsedOptionValue* parsedValue )
{
    ELASTICAPM_ASSERT_VALID_PTR( optMeta );
    ELASTICAPM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_duration );
    ELASTICAPM_ASSERT_VALID_PTR( rawValue );
    ELASTICAPM_ASSERT_VALID_PTR( parsedValue );
    ELASTICAPM_ASSERT_EQ_UINT64( parsedValue->type, parsedOptionValueType_undefined );

    ResultCode parseResultCode = parseDuration( stringToStringView( rawValue )
                                                , optMeta->additionalData.durationData.defaultUnits
                                                , /* out */ &parsedValue->u.durationValue );
    if ( parseResultCode == resultSuccess ) parsedValue->type = parsedOptionValueType_duration;
    return parseResultCode;
}

static String streamParsedDuration( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, TextOutputStream* txtOutStream )
{
    ELASTICAPM_ASSERT_VALID_PTR( optMeta );
    ELASTICAPM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_duration );
    ELASTICAPM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue );
    ELASTICAPM_ASSERT_EQ_UINT64( parsedValue.type, optMeta->defaultValue.type );

    return streamDuration( parsedValue.u.durationValue, txtOutStream );
}

static void parsedDurationValueToZval( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, zval* return_value )
{
    ELASTICAPM_ASSERT_VALID_PTR( optMeta );
    ELASTICAPM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_duration );
    ELASTICAPM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue );
    ELASTICAPM_ASSERT_EQ_UINT64( parsedValue.type, optMeta->defaultValue.type );
    ELASTICAPM_ASSERT_VALID_PTR( return_value );

    RETURN_DOUBLE( durationToMilliseconds( parsedValue.u.durationValue ) )
}

static
ResultCode parseEnumValue( const OptionMetadata* optMeta, String rawValue, /* out */ ParsedOptionValue* parsedValue )
{
    ELASTICAPM_ASSERT_VALID_PTR( optMeta );
    ELASTICAPM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_int );
    ELASTICAPM_ASSERT_VALID_PTR( rawValue );
    ELASTICAPM_ASSERT_VALID_PTR( parsedValue );
    ELASTICAPM_ASSERT_EQ_UINT64( parsedValue->type, parsedOptionValueType_undefined );

    int foundMatch = -1;
    StringView rawValueStrView = stringToStringView( rawValue );

    ELASTICAPM_FOR_EACH_INDEX( i, optMeta->additionalData.enumData.enumElementsCount )
    {
        StringView currentEnumName = stringToStringView( optMeta->additionalData.enumData.names[ i ] );
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
            // then it's ambiguous and we return failure
            if ( foundMatch != -1 )
            {
                ELASTICAPM_LOG_ERROR(
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
    ELASTICAPM_ASSERT_VALID_PTR( optMeta );
    ELASTICAPM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_int );
    ELASTICAPM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue );
    ELASTICAPM_ASSERT_EQ_UINT64( parsedValue.type, optMeta->defaultValue.type );
    ELASTICAPM_ASSERT_VALID_PTR( return_value );

    RETURN_LONG( (long)( parsedValue.u.intValue ) )
}

static String streamParsedLogLevel( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, TextOutputStream* txtOutStream )
{
    ELASTICAPM_ASSERT_VALID_PTR( optMeta );
    ELASTICAPM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_int );
    ELASTICAPM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue );
    ELASTICAPM_ASSERT_EQ_UINT64( parsedValue.type, optMeta->defaultValue.type );

    return streamLogLevel( (LogLevel) parsedValue.u.intValue, txtOutStream );
}

static OptionMetadata buildStringOptionMetadata(
        bool isSecret
        , String name
        , StringView iniName
        , String defaultValue
        , SetConfigSnapshotFieldFunc setFieldFunc
        , GetConfigSnapshotFieldFunc getFieldFunc )
{
    return (OptionMetadata)
    {
        .isSecret = isSecret,
        .name = name,
        .iniName = iniName,
        .defaultValue = { .type = parsedOptionValueType_string, .u.stringValue = defaultValue },
        .interpretIniRawValue = &interpretStringIniRawValue,
        .parseRawValue = &parseStringValue,
        .streamParsedValue = &streamParsedString,
        .setField = setFieldFunc,
        .getField = getFieldFunc,
        .parsedValueToZval = &parsedStringValueToZval
    };
}

static OptionMetadata buildBoolOptionMetadata(
        bool isSecret
        , String name
        , StringView iniName
        , bool defaultValue
        , SetConfigSnapshotFieldFunc setFieldFunc
        , GetConfigSnapshotFieldFunc getFieldFunc )
{
    return (OptionMetadata)
    {
        .isSecret = isSecret,
        .name = name,
        .iniName = iniName,
        .defaultValue = { .type = parsedOptionValueType_bool, .u.boolValue = defaultValue },
        .interpretIniRawValue = &interpretBoolIniRawValue,
        .parseRawValue = &parseBoolValue,
        .streamParsedValue = &streamParsedBool,
        .setField = setFieldFunc,
        .getField = getFieldFunc,
        .parsedValueToZval = &parsedBoolValueToZval
    };
}

static OptionMetadata buildDurationOptionMetadata(
        bool isSecret
        , String name
        , StringView iniName
        , Duration defaultValue
        , SetConfigSnapshotFieldFunc setFieldFunc
        , GetConfigSnapshotFieldFunc getFieldFunc
        , DurationUnits defaultUnits )
{
    return (OptionMetadata)
    {
        .isSecret = isSecret,
        .name = name,
        .iniName = iniName,
        .defaultValue = { .type = parsedOptionValueType_duration, .u.durationValue = defaultValue },
        .interpretIniRawValue = &interpretStringIniRawValue,
        .parseRawValue = &parseDurationValue,
        .streamParsedValue = &streamParsedDuration,
        .setField = setFieldFunc,
        .getField = getFieldFunc,
        .parsedValueToZval = &parsedDurationValueToZval,
        .additionalData = (OptionAdditionalMetadata){ .durationData = (DurationOptionAdditionalMetadata){ .defaultUnits = defaultUnits } }
    };
}

static OptionMetadata buildEnumOptionMetadata(
        bool isSecret
        , String name
        , StringView iniName
        , int defaultValue
        , InterpretIniRawValueFunc interpretIniRawValue
        , SetConfigSnapshotFieldFunc setFieldFunc
        , GetConfigSnapshotFieldFunc getFieldFunc
        , StreamParsedValueFunc streamParsedValue
        , EnumOptionAdditionalMetadata additionalMetadata )
{
    return (OptionMetadata)
    {
        .isSecret = isSecret,
        .name = name,
        .iniName = iniName,
        .defaultValue = { .type = parsedOptionValueType_int, .u.intValue = defaultValue },
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
    ELASTICAPM_ASSERT_VALID_PTR( optsMeta );
    ELASTICAPM_ASSERT_VALID_OPTION_ID( actualOptId );
    ELASTICAPM_ASSERT_VALID_OPTION_ID( expectedOptId );
    ELASTICAPM_ASSERT_EQ_UINT64( actualOptId, expectedOptId );

    ELASTICAPM_FOR_EACH_INDEX( i, actualOptId )
        ELASTICAPM_ASSERT( ! areStringsEqualIgnoringCase( optsMeta[ i ].name, optionMetadata.name )
        , "i: %u, optionMetadata.name: %s", (unsigned int)i, optionMetadata.name );

    optsMeta[ actualOptId ] = optionMetadata;
}

#define ELASTICAPM_FREE_AND_RESET_FIELD_FUNC_NAME( fieldName ) freeAndReset_ConfigSnapshot_##fieldName##_field
#define ELASTICAPM_SET_FIELD_FUNC_NAME( fieldName ) set_ConfigSnapshot_##fieldName##_field
#define ELASTICAPM_GET_FIELD_FUNC_NAME( fieldName ) get_ConfigSnapshot_##fieldName##_field

#define ELASTICAPM_DEFINE_FIELD_ACCESS_FUNCS( unionFieldForType, fieldName ) \
    static void ELASTICAPM_SET_FIELD_FUNC_NAME( fieldName ) ( const OptionMetadata* optMeta, ParsedOptionValue parsedValue, ConfigSnapshot* dst ) \
    { \
        ELASTICAPM_ASSERT_VALID_PTR( optMeta ); \
        ELASTICAPM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue ); \
        ELASTICAPM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedValue.type ); \
        ELASTICAPM_ASSERT_VALID_PTR( dst ); \
        \
        dst->fieldName = parsedValue.u.unionFieldForType; \
    } \
    \
    static ParsedOptionValue ELASTICAPM_GET_FIELD_FUNC_NAME( fieldName ) ( const OptionMetadata* optMeta, const ConfigSnapshot* src ) \
    { \
        ELASTICAPM_ASSERT_VALID_PTR( optMeta ); \
        ELASTICAPM_ASSERT_VALID_PTR( src ); \
        \
        return (ParsedOptionValue){ .type = optMeta->defaultValue.type, .u.unionFieldForType = src->fieldName }; \
    }

#define ELASTICAPM_DEFINE_ENUM_FIELD_ACCESS_FUNCS( EnumType, fieldName ) \
    static void ELASTICAPM_SET_FIELD_FUNC_NAME( fieldName ) ( const OptionMetadata* optMeta, ParsedOptionValue parsedValue,  ConfigSnapshot* dst ) \
    { \
        ELASTICAPM_ASSERT_VALID_PTR( optMeta ); \
        ELASTICAPM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_int ); \
        ELASTICAPM_ASSERT_VALID_PARSED_OPTION_VALUE( parsedValue ); \
        ELASTICAPM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedValue.type ); \
        ELASTICAPM_ASSERT_VALID_PTR( dst ); \
        \
        dst->fieldName = (EnumType)( parsedValue.u.intValue ); \
    } \
    \
    static ParsedOptionValue ELASTICAPM_GET_FIELD_FUNC_NAME( fieldName ) ( const OptionMetadata* optMeta, const ConfigSnapshot* src ) \
    { \
        ELASTICAPM_ASSERT_VALID_PTR( optMeta ); \
        ELASTICAPM_ASSERT_EQ_UINT64( optMeta->defaultValue.type, parsedOptionValueType_int ); \
        ELASTICAPM_ASSERT_VALID_PTR( src ); \
        \
        return (ParsedOptionValue){ .type = optMeta->defaultValue.type, .u.intValue = (int)( src->fieldName ) }; \
    }

#   ifdef PHP_WIN32
ELASTICAPM_DEFINE_FIELD_ACCESS_FUNCS( boolValue, allowAbortDialog )
#   endif
ELASTICAPM_DEFINE_FIELD_ACCESS_FUNCS( boolValue, abortOnMemoryLeak )
#   if ( ELASTICAPM_ASSERT_ENABLED_01 != 0 )
ELASTICAPM_DEFINE_ENUM_FIELD_ACCESS_FUNCS( AssertLevel, assertLevel )
#   endif
ELASTICAPM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, bootstrapPhpPartFile )
ELASTICAPM_DEFINE_FIELD_ACCESS_FUNCS( boolValue, enabled )
ELASTICAPM_DEFINE_ENUM_FIELD_ACCESS_FUNCS( InternalChecksLevel, internalChecksLevel )
ELASTICAPM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, logFile )
ELASTICAPM_DEFINE_ENUM_FIELD_ACCESS_FUNCS( LogLevel, logLevel )
ELASTICAPM_DEFINE_ENUM_FIELD_ACCESS_FUNCS( LogLevel, logLevelFile )
ELASTICAPM_DEFINE_ENUM_FIELD_ACCESS_FUNCS( LogLevel, logLevelStderr )
#   ifndef PHP_WIN32
ELASTICAPM_DEFINE_ENUM_FIELD_ACCESS_FUNCS( LogLevel, logLevelSyslog )
#   endif
#   ifdef PHP_WIN32
ELASTICAPM_DEFINE_ENUM_FIELD_ACCESS_FUNCS( LogLevel, logLevelWinSysDebug )
#   endif
#   if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )
ELASTICAPM_DEFINE_ENUM_FIELD_ACCESS_FUNCS( MemoryTrackingLevel, memoryTrackingLevel )
#   endif
ELASTICAPM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, secretToken )
ELASTICAPM_DEFINE_FIELD_ACCESS_FUNCS( durationValue, serverConnectTimeout )
ELASTICAPM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, serverUrl )
ELASTICAPM_DEFINE_FIELD_ACCESS_FUNCS( stringValue, serviceName )

#undef ELASTICAPM_DEFINE_FIELD_ACCESS_FUNCS
#undef ELASTICAPM_DEFINE_ENUM_FIELD_ACCESS_FUNCS

#define ELASTICAPM_INIT_METADATA_EX( buildFunc, fieldName, isSecret, optName, defaultValue, ... ) \
    initOptionMetadataForId \
    ( \
        optsMeta \
        , (OptionId)(i++) \
        , optionId_##fieldName \
        , buildFunc \
        ( \
            isSecret \
            , optName \
            , ELASTICAPM_STRING_LITERAL_TO_VIEW( ELASTICAPM_CFG_CONVERT_OPT_NAME_TO_INI_NAME( optName ) ) \
            , defaultValue \
            , ELASTICAPM_SET_FIELD_FUNC_NAME( fieldName ) \
            , ELASTICAPM_GET_FIELD_FUNC_NAME( fieldName ) \
            , ##__VA_ARGS__ \
        ) \
    )

#define ELASTICAPM_INIT_METADATA( buildFunc, fieldName, optName, defaultValue ) \
    ELASTICAPM_INIT_METADATA_EX( buildFunc, fieldName, /* isSecret */ false, optName, defaultValue )

#define ELASTICAPM_INIT_DURATION_METADATA( fieldName, optName, defaultValue, defaultUnits ) \
    ELASTICAPM_INIT_METADATA_EX( buildDurationOptionMetadata, fieldName, /* isSecret */ false, optName, defaultValue, defaultUnits )

#define ELASTICAPM_INIT_SECRET_METADATA( buildFunc, fieldName, optName, defaultValue ) \
    ELASTICAPM_INIT_METADATA_EX( buildFunc, fieldName, /* isSecret */ true, optName, defaultValue )

#define ELASTICAPM_ENUM_INIT_METADATA( fieldName, optName, defaultValue, interpretIniRawValue, enumNamesArray, isUniquePrefixEnoughArg ) \
    initOptionMetadataForId \
    ( \
        optsMeta \
        , (OptionId)(i++) \
        , optionId_##fieldName \
        , buildEnumOptionMetadata \
        ( \
            /* isSecret */ false \
            , optName \
            , ELASTICAPM_STRING_LITERAL_TO_VIEW( ELASTICAPM_CFG_CONVERT_OPT_NAME_TO_INI_NAME( optName ) ) \
            , defaultValue \
            , interpretIniRawValue \
            , ELASTICAPM_SET_FIELD_FUNC_NAME( fieldName ) \
            , ELASTICAPM_GET_FIELD_FUNC_NAME( fieldName ) \
            , &streamParsedLogLevel \
            , (EnumOptionAdditionalMetadata) \
            { \
                .names = (enumNamesArray), \
                .enumElementsCount = ELASTICAPM_STATIC_ARRAY_SIZE( (enumNamesArray) ), \
                .isUniquePrefixEnough = (isUniquePrefixEnoughArg) \
            } \
        ) \
    )

#define ELASTICAPM_INIT_LOG_LEVEL_METADATA( fieldName, optName ) \
    ELASTICAPM_ENUM_INIT_METADATA( fieldName, optName, logLevel_not_set, &interpretEmptyIniRawValueAsOff, logLevelNames, /* isUniquePrefixEnough: */ true )

static void initOptionsMetadata( OptionMetadata* optsMeta )
{
    ELASTICAPM_ASSERT_VALID_PTR( optsMeta );

    size_t i = 0;

    //
    // The order of calls to ELASTICAPM_INIT_METADATA below should be the same as in OptionId
    //

    #ifdef PHP_WIN32
    ELASTICAPM_INIT_METADATA(
            buildBoolOptionMetadata,
            allowAbortDialog,
            ELASTICAPM_CFG_OPT_NAME_ALLOW_ABORT_DIALOG,
            /* defaultValue: */ false );
    #endif

    ELASTICAPM_INIT_METADATA(
            buildBoolOptionMetadata,
            abortOnMemoryLeak,
            ELASTICAPM_CFG_OPT_NAME_ABORT_ON_MEMORY_LEAK,
            /* defaultValue: */ ELASTICAPM_MEMORY_TRACKING_DEFAULT_ABORT_ON_MEMORY_LEAK );

    #if ( ELASTICAPM_ASSERT_ENABLED_01 != 0 )
    ELASTICAPM_ENUM_INIT_METADATA(
            /* fieldName: */ assertLevel,
            /* optName: */ ELASTICAPM_CFG_OPT_NAME_ASSERT_LEVEL,
            /* defaultValue: */ assertLevel_not_set,
            &interpretEmptyIniRawValueAsOff,
            assertLevelNames,
            /* isUniquePrefixEnough: */ true );
    #endif

    ELASTICAPM_INIT_METADATA(
            buildStringOptionMetadata,
            bootstrapPhpPartFile,
            ELASTICAPM_CFG_OPT_NAME_BOOTSTRAP_PHP_PART_FILE,
            /* defaultValue: */ NULL );

    ELASTICAPM_INIT_METADATA(
            buildBoolOptionMetadata,
            enabled,
            ELASTICAPM_CFG_OPT_NAME_ENABLED,
            /* defaultValue: */ true );

    ELASTICAPM_ENUM_INIT_METADATA(
            /* fieldName: */ internalChecksLevel,
            /* optName: */ ELASTICAPM_CFG_OPT_NAME_INTERNAL_CHECKS_LEVEL,
            /* defaultValue: */ internalChecksLevel_not_set,
            &interpretEmptyIniRawValueAsOff,
            internalChecksLevelNames,
            /* isUniquePrefixEnough: */ true );

    ELASTICAPM_INIT_METADATA(
            buildStringOptionMetadata,
            logFile,
            ELASTICAPM_CFG_OPT_NAME_LOG_FILE,
            /* defaultValue: */ NULL );

    ELASTICAPM_INIT_LOG_LEVEL_METADATA(
            logLevel,
            ELASTICAPM_CFG_OPT_NAME_LOG_LEVEL );
    ELASTICAPM_INIT_LOG_LEVEL_METADATA(
            logLevelFile,
            ELASTICAPM_CFG_OPT_NAME_LOG_LEVEL_FILE );
    ELASTICAPM_INIT_LOG_LEVEL_METADATA(
            logLevelStderr,
            ELASTICAPM_CFG_OPT_NAME_LOG_LEVEL_STDERR );
    #ifndef PHP_WIN32
    ELASTICAPM_INIT_LOG_LEVEL_METADATA(
            logLevelSyslog,
            ELASTICAPM_CFG_OPT_NAME_LOG_LEVEL_SYSLOG );
    #endif
    #ifdef PHP_WIN32
    ELASTICAPM_INIT_LOG_LEVEL_METADATA(
            logLevelWinSysDebug,
            ELASTICAPM_CFG_OPT_NAME_LOG_LEVEL_WIN_SYS_DEBUG );
    #endif

    #if ( ELASTICAPM_MEMORY_TRACKING_ENABLED_01 != 0 )
    ELASTICAPM_ENUM_INIT_METADATA(
            /* fieldName: */ memoryTrackingLevel,
            /* optName: */ ELASTICAPM_CFG_OPT_NAME_MEMORY_TRACKING_LEVEL,
            /* defaultValue: */ memoryTrackingLevel_not_set,
            &interpretEmptyIniRawValueAsOff,
            memoryTrackingLevelNames,
            /* isUniquePrefixEnough: */ true );
    #endif

    ELASTICAPM_INIT_SECRET_METADATA(
            buildStringOptionMetadata,
            secretToken,
            ELASTICAPM_CFG_OPT_NAME_SECRET_TOKEN,
            /* defaultValue: */ NULL );

    ELASTICAPM_INIT_DURATION_METADATA(
            serverConnectTimeout
            , ELASTICAPM_CFG_OPT_NAME_SERVER_CONNECT_TIMEOUT
            , /* defaultValue: */ makeDuration( 5, durationUnits_seconds )
            , /* defaultUnits */ durationUnits_seconds );

    ELASTICAPM_INIT_METADATA(
            buildStringOptionMetadata,
            serverUrl,
            ELASTICAPM_CFG_OPT_NAME_SERVER_URL,
            /* defaultValue: */ "http://localhost:8200" );

    ELASTICAPM_INIT_METADATA(
            buildStringOptionMetadata,
            serviceName,
            ELASTICAPM_CFG_OPT_NAME_SERVICE_NAME,
            /* defaultValue: */ "Unknown PHP service" );

    ELASTICAPM_ASSERT_EQ_UINT64( i, numberOfOptions );
}

#undef ELASTICAPM_SET_FIELD_FUNC_NAME
#undef ELASTICAPM_GET_FIELD_FUNC_NAME
#undef ELASTICAPM_FREE_AND_RESET_FIELD_FUNC_NAME

#undef ELASTICAPM_INIT_METADATA
#undef ELASTICAPM_INIT_LOG_LEVEL_METADATA

static
void parseCombinedRawConfigSnapshot(
        const OptionMetadata* optsMeta,
        const CombinedRawConfigSnapshot* combinedRawCfgSnapshot,
        ConfigSnapshot* cfgSnapshot )
{
    ELASTICAPM_ASSERT_VALID_PTR( optsMeta );
    ELASTICAPM_ASSERT_VALID_PTR( combinedRawCfgSnapshot );
    ELASTICAPM_ASSERT_VALID_PTR( cfgSnapshot );

    ELASTICAPM_FOR_EACH_OPTION_ID( optId )
    {
        char txtOutStreamBuf[ ELASTICAPM_TEXT_OUTPUT_STREAM_ON_STACK_BUFFER_SIZE ];
        TextOutputStream txtOutStream = ELASTICAPM_TEXT_OUTPUT_STREAM_FROM_STATIC_BUFFER( txtOutStreamBuf );
        const OptionMetadata* const optMeta = &( optsMeta[ optId ] );
        const String originalRawValue = combinedRawCfgSnapshot->original[ optId ];
        const String interpretedRawValue = combinedRawCfgSnapshot->interpreted[ optId ];
        const String sourceDescription = combinedRawCfgSnapshot->sourceDescriptions[ optId ];
        ParsedOptionValue parsedOptValue;
        ELASTICAPM_ZERO_STRUCT( &parsedOptValue );

        if ( interpretedRawValue == NULL )
        {
            parsedOptValue = optMeta->defaultValue;
            ELASTICAPM_LOG_DEBUG(
                    "Configuration option `%s' is not set - using default value (%s)",
                    optMeta->name,
                    optMeta->streamParsedValue( optMeta, parsedOptValue, &txtOutStream ) );
        }
        else if ( optMeta->parseRawValue( optMeta, interpretedRawValue, &parsedOptValue ) == resultSuccess )
        {
            ELASTICAPM_LOG_DEBUG(
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
            ELASTICAPM_LOG_ERROR(
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
    ELASTICAPM_ASSERT_VALID_STRING( optName );
    ELASTICAPM_ASSERT_VALID_OUT_PTR_TO_PTR( envVarName );

    ResultCode resultCode;
    StringView envVarNamePrefix = ELASTICAPM_STRING_LITERAL_TO_VIEW( "ELASTIC_APM_" );
    MutableString envVarNameBuffer = NULL;
    const size_t envVarNameBufferSize = envVarNamePrefix.length + strlen( optName ) + 1;

    ELASTICAPM_PEMALLOC_STRING_IF_FAILED_GOTO( envVarNameBufferSize, envVarNameBuffer );
    strcpy( envVarNameBuffer, envVarNamePrefix.begin );
    copyStringAsUpperCase( optName, /* out */ envVarNameBuffer + envVarNamePrefix.length );

    resultCode = resultSuccess;
    *envVarName = envVarNameBuffer;

    finally:
    return resultCode;

    failure:
    ELASTICAPM_PEFREE_STRING_AND_SET_TO_NULL( envVarNameBufferSize, envVarNameBuffer );
    goto finally;
}

static void destructEnvVarNames( /* in,out */ String envVarNames[] )
{
    ELASTICAPM_ASSERT_VALID_PTR( envVarNames );

    ELASTICAPM_FOR_EACH_OPTION_ID( optId )
        ELASTICAPM_PEFREE_STRING_AND_SET_TO_NULL( strlen( envVarNames[ optId ] ) + 1, envVarNames[ optId ] );
}

static ResultCode constructEnvVarNames( OptionMetadata* optsMeta, /* out */ String envVarNames[] )
{
    ELASTICAPM_ASSERT_VALID_PTR( optsMeta );
    ELASTICAPM_ASSERT_VALID_PTR( envVarNames );

    ResultCode resultCode;

    ELASTICAPM_FOR_EACH_OPTION_ID( optId )
        ELASTICAPM_CALL_IF_FAILED_GOTO( constructEnvVarNameForOption( optsMeta[ optId ].name, &envVarNames[ optId ] ) );

    resultCode = resultSuccess;

    finally:
    return resultCode;

    failure:
    destructEnvVarNames( envVarNames );
    goto finally;
}

#ifdef ELASTICAPM_GETENV_FUNC
// Declare to avoid warnings
char* ELASTICAPM_MOCK_GETENV_FUNC( const char* name );
#else
#define ELASTICAPM_GETENV_FUNC getenv
#endif

String readRawOptionValueFromEnvVars( const ConfigManager* cfgManager, OptionId optId )
{
    ELASTICAPM_ASSERT_VALID_PTR( cfgManager );
    ELASTICAPM_ASSERT_VALID_OPTION_ID( optId );

    return ELASTICAPM_GETENV_FUNC( cfgManager->meta.envVarNames[ optId ] );
}

static
ResultCode getRawOptionValueFromEnvVars(
        const ConfigManager* cfgManager,
        OptionId optId,
        String* originalRawValue,
        String* interpretedRawValue )
{
    ELASTICAPM_ASSERT_VALID_PTR( cfgManager );
    ELASTICAPM_ASSERT_VALID_OPTION_ID( optId );
    ELASTICAPM_ASSERT_VALID_OUT_PTR_TO_PTR( originalRawValue );
    ELASTICAPM_ASSERT_VALID_OUT_PTR_TO_PTR( interpretedRawValue );

    ResultCode resultCode;
    String returnedRawValue;
    String rawValue = NULL;

    returnedRawValue = readRawOptionValueFromEnvVars( cfgManager, optId );
    if ( returnedRawValue != NULL )
        ELASTICAPM_PEMALLOC_DUP_STRING_IF_FAILED_GOTO( returnedRawValue, rawValue );

    resultCode = resultSuccess;
    *originalRawValue = rawValue;
    *interpretedRawValue = *originalRawValue;

    finally:
    return resultCode;

    failure:
    ELASTICAPM_PEFREE_STRING_AND_SET_TO_NULL( strlen( rawValue ) + 1, rawValue );
    goto finally;
}

String readRawOptionValueFromIni(
        const ConfigManager* cfgManager,
        OptionId optId,
        bool* exists )
{
    ELASTICAPM_ASSERT_VALID_PTR( cfgManager );
    ELASTICAPM_ASSERT_VALID_OPTION_ID( optId );

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
    ELASTICAPM_ASSERT_VALID_PTR( cfgManager );
    ELASTICAPM_ASSERT_VALID_OPTION_ID( optId );
    ELASTICAPM_ASSERT_VALID_OUT_PTR_TO_PTR( originalRawValue );
    ELASTICAPM_ASSERT_VALID_OUT_PTR_TO_PTR( interpretedRawValue );

    ResultCode resultCode;
    bool exists = 0;
    String returnedRawValue = NULL;
    String rawValue = NULL;
    const OptionMetadata* const optMeta = &( cfgManager->meta.optionsMeta[ optId ] );

    returnedRawValue = readRawOptionValueFromIni( cfgManager,optId, &exists );

    if ( exists && ( returnedRawValue != NULL ) )
        ELASTICAPM_PEMALLOC_DUP_STRING_IF_FAILED_GOTO( returnedRawValue, rawValue );

    resultCode = resultSuccess;
    *originalRawValue = rawValue;
    *interpretedRawValue = optMeta->interpretIniRawValue( rawValue );

    finally:
    return resultCode;

    failure:
    ELASTICAPM_PEFREE_STRING_AND_SET_TO_NULL( strlen( rawValue ) + 1, rawValue );
    goto finally;
}

static void initRawConfigSources( RawConfigSnapshotSource rawCfgSources[ numberOfRawConfigSources ] )
{
    ELASTICAPM_ASSERT_VALID_PTR( rawCfgSources );

    size_t i = 0;

    ELASTICAPM_ASSERT_EQ_UINT64( i, rawConfigSourceId_iniFile );
    rawCfgSources[ i++ ] = (RawConfigSnapshotSource)
    {
        .description = "INI file",
        .getOptionValue = &getRawOptionValueFromIni
    };

    ELASTICAPM_ASSERT_EQ_UINT64( i, rawConfigSourceId_envVars );
    rawCfgSources[ i++ ] = (RawConfigSnapshotSource)
    {
        .description = "Environment variables",
        .getOptionValue = &getRawOptionValueFromEnvVars
    };

    ELASTICAPM_ASSERT_EQ_UINT64( i, numberOfRawConfigSources );
}

static
void deleteConfigRawDataAndSetToNull( /* in,out */ ConfigRawData** pRawData )
{
    ELASTICAPM_ASSERT_VALID_PTR( pRawData );

    ConfigRawData* rawData = *pRawData;
    if ( rawData == NULL ) return;
    ELASTICAPM_ASSERT_VALID_PTR( rawData );

    ELASTICAPM_FOR_EACH_OPTION_ID( optId )
        ELASTICAPM_FOR_EACH_INDEX( rawSourceIndex, numberOfRawConfigSources )
        {
            const char** pOriginalRawValue = &( rawData->fromSources[ rawSourceIndex ].original[ optId ] );
            ELASTICAPM_PEFREE_STRING_AND_SET_TO_NULL(strlen( *pOriginalRawValue ) + 1, *pOriginalRawValue );
        }

    ELASTICAPM_PEFREE_INSTANCE_AND_SET_TO_NULL( ConfigRawData, *pRawData );
}

static
ResultCode fetchConfigRawDataFromAllSources( const ConfigManager* cfgManager, /* out */ ConfigRawData* newRawData )
{
    ELASTICAPM_ASSERT_VALID_PTR( cfgManager );
    ELASTICAPM_ASSERT_VALID_PTR( newRawData );

    ResultCode resultCode;

    ELASTICAPM_FOR_EACH_OPTION_ID( optId )
    {
        ELASTICAPM_FOR_EACH_INDEX( rawCfgSourceIndex, numberOfRawConfigSources )
        {
            ELASTICAPM_CALL_IF_FAILED_GOTO( cfgManager->meta.rawCfgSources[ rawCfgSourceIndex ].getOptionValue(
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
    ELASTICAPM_ASSERT_VALID_PTR( cfgManager );
    ELASTICAPM_ASSERT_VALID_PTR( newRawData );

    ELASTICAPM_FOR_EACH_OPTION_ID( optId )
    {
        ELASTICAPM_FOR_EACH_INDEX( rawCfgSourceIndex, numberOfRawConfigSources )
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
    ELASTICAPM_ASSERT_VALID_PTR( cfgManager );
    ELASTICAPM_ASSERT_VALID_OUT_PTR_TO_PTR( pNewRawData );

    ResultCode resultCode;
    ConfigRawData* newRawData = NULL;

    ELASTICAPM_PEMALLOC_INSTANCE_IF_FAILED_GOTO( ConfigRawData, newRawData );
    ELASTICAPM_ZERO_STRUCT( newRawData );

    ELASTICAPM_CALL_IF_FAILED_GOTO( fetchConfigRawDataFromAllSources( cfgManager, /* out */ newRawData ) );
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
    ELASTICAPM_ASSERT_VALID_PTR( snapshot1 );
    ELASTICAPM_ASSERT_VALID_PTR( snapshot2 );

    ELASTICAPM_FOR_EACH_OPTION_ID( optId )
        if ( ( ! areEqualNullableStrings( snapshot1->original[ optId ], snapshot2->original[ optId ] ) ) ||
                ( ! areEqualNullableStrings( snapshot1->interpreted[ optId ], snapshot2->interpreted[ optId ] ) ) ||
                ( ! areEqualNullableStrings( snapshot1->sourceDescriptions[ optId ], snapshot2->sourceDescriptions[ optId ] ) ) )
            return false;

    return true;
}

static
void logConfigChange( const ConfigManager* cfgManager, const ConfigRawData* newRawData )
{
    ELASTICAPM_ASSERT_VALID_PTR( cfgManager );
    ELASTICAPM_ASSERT_VALID_PTR( newRawData );

    // TODO: Sergey Kleyman: Implement: logConfigChange
}

const ConfigSnapshot* getConfigManagerCurrentSnapshot( const ConfigManager* cfgManager )
{
    ELASTICAPM_ASSERT_VALID_PTR( cfgManager );
    return &cfgManager->current.snapshot;
}

ResultCode ensureConfigManagerHasLatestConfig( ConfigManager* cfgManager, bool* didConfigChange )
{
    ELASTICAPM_ASSERT_VALID_PTR( cfgManager );
    ELASTICAPM_ASSERT_VALID_PTR( didConfigChange );

    ResultCode resultCode;
    ConfigRawData* newRawData = NULL;
    ConfigSnapshot newCfgSnapshot = { 0 };

    ELASTICAPM_CALL_IF_FAILED_GOTO( fetchConfigRawData( cfgManager, &newRawData ) );

    if ( cfgManager->current.rawData != NULL &&
            areEqualCombinedRawConfigSnapshots( &cfgManager->current.rawData->combined, &newRawData->combined ) )
    {
        ELASTICAPM_LOG_DEBUG( "Current configuration is already the latest" );
        resultCode = resultSuccess;
        *didConfigChange = false;
        goto finally;
    }

    parseCombinedRawConfigSnapshot( cfgManager->meta.optionsMeta, &newRawData->combined, &newCfgSnapshot );
    logConfigChange( cfgManager, newRawData );
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
    ELASTICAPM_ASSERT_VALID_PTR( cfgManagerCurrent );

    deleteConfigRawDataAndSetToNull( /* in,out */ &cfgManagerCurrent->rawData );

    ELASTICAPM_ZERO_STRUCT( cfgManagerCurrent );
}

static
void initConfigManagerCurrentState(
        const ConfigMetadata* cfgManagerMeta,
        /* out */ ConfigManagerCurrentState* cfgManagerCurrent )
{
    ELASTICAPM_ASSERT_VALID_PTR( cfgManagerMeta );
    ELASTICAPM_ASSERT_VALID_PTR( cfgManagerCurrent );

    ELASTICAPM_FOR_EACH_OPTION_ID( optId )
    {
        const OptionMetadata* const optMeta = &( cfgManagerMeta->optionsMeta[ optId ] );
        optMeta->setField( optMeta, optMeta->defaultValue, &cfgManagerCurrent->snapshot );
    }
}

static
void destructConfigManagerMetadata( ConfigMetadata* cfgManagerMeta )
{
    ELASTICAPM_ASSERT_VALID_PTR( cfgManagerMeta );

    destructEnvVarNames( /* in,out */ cfgManagerMeta->envVarNames );

    ELASTICAPM_ZERO_STRUCT( cfgManagerMeta );
}

static
ResultCode constructConfigManagerMetadata( ConfigMetadata* cfgManagerMeta )
{
    ELASTICAPM_ASSERT_VALID_PTR( cfgManagerMeta );

    ResultCode resultCode;

    initOptionsMetadata( cfgManagerMeta->optionsMeta );
    ELASTICAPM_CALL_IF_FAILED_GOTO( constructEnvVarNames( cfgManagerMeta->optionsMeta, /* out */ cfgManagerMeta->envVarNames ) );
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
    ELASTICAPM_ASSERT_VALID_PTR( result );
    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( &result->txtOutStream );

    const OptionMetadata* optMeta = NULL;
    ELASTICAPM_FOR_EACH_OPTION_ID( optId )
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
    ELASTICAPM_ASSERT_VALID_PTR( cfgManager );
    ELASTICAPM_ASSERT_VALID_PTR( result );

    const OptionMetadata* const optMeta = &( cfgManager->meta.optionsMeta[ optId ] );
    result->isSecret = optMeta->isSecret;
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
    ELASTICAPM_ASSERT_VALID_PTR( cfgManager );
    ELASTICAPM_ASSERT_VALID_OPTION_ID( optId );
    ELASTICAPM_ASSERT_VALID_PTR( result );
    ELASTICAPM_ASSERT_VALID_PTR_TEXT_OUTPUT_STREAM( &result->txtOutStream );

    const OptionMetadata* const optMeta = &( cfgManager->meta.optionsMeta[ optId ] );
    result->streamedParsedValue = optMeta->streamParsedValue(
            optMeta, optMeta->getField( optMeta, &( cfgManager->current.snapshot ) ), &result->txtOutStream );
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
    ELASTICAPM_ASSERT_VALID_PTR( cfgManager );
    ELASTICAPM_ASSERT_VALID_OPTION_ID( optId );
    ELASTICAPM_ASSERT_LT_UINT64( rawCfgSourceId, numberOfRawConfigSources );
    ELASTICAPM_ASSERT_VALID_OUT_PTR_TO_PTR( originalRawValue );
    ELASTICAPM_ASSERT_VALID_OUT_PTR_TO_PTR( interpretedRawValue );

    *originalRawValue = cfgManager->current.rawData->fromSources[ rawCfgSourceId ].original[ optId ];
    *interpretedRawValue = cfgManager->current.rawData->fromSources[ rawCfgSourceId ].interpreted[ optId ];
}

void deleteConfigManagerAndSetToNull( ConfigManager** pCfgManager )
{
    ELASTICAPM_ASSERT_VALID_PTR( pCfgManager );

    ConfigManager* const cfgManager = *pCfgManager;
    if ( cfgManager == NULL ) return;
    ELASTICAPM_ASSERT_VALID_PTR( cfgManager );

    destructConfigManagerCurrentState( /* in,out */ &cfgManager->current );
    destructConfigManagerMetadata( /* in,out */ &cfgManager->meta );

    ELASTICAPM_ZERO_STRUCT( cfgManager );

    ELASTICAPM_PEFREE_INSTANCE_AND_SET_TO_NULL( ConfigManager, *pCfgManager );
}

ResultCode newConfigManager( ConfigManager** pNewCfgManager )
{
    ELASTICAPM_ASSERT_VALID_OUT_PTR_TO_PTR( pNewCfgManager );

    ResultCode resultCode;
    ConfigManager* cfgManager = NULL;

    ELASTICAPM_PEMALLOC_INSTANCE_IF_FAILED_GOTO( ConfigManager, cfgManager );
    ELASTICAPM_ZERO_STRUCT( cfgManager );

    ELASTICAPM_CALL_IF_FAILED_GOTO( constructConfigManagerMetadata( /* out */ &cfgManager->meta ) );
    initConfigManagerCurrentState( &cfgManager->meta, /* out */ &cfgManager->current );

    resultCode = resultSuccess;
    *pNewCfgManager = cfgManager;

    finally:
    return resultCode;

    failure:
    deleteConfigManagerAndSetToNull( /* in,out */ &cfgManager );
    goto finally;
}
