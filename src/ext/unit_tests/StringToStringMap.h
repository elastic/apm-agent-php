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

#pragma once

#include <stdbool.h>
#include "basic_types.h"

struct StringToStringMap;
typedef struct StringToStringMap StringToStringMap;

StringToStringMap* newStringToStringMap();
void deleteStringToStringMapAndSetToNull( StringToStringMap** pMap );
void setStringToStringMapEntry( StringToStringMap* map, String key, String value );
bool getStringToStringMapEntry( const StringToStringMap* map, String key, String* value );
void deleteStringToStringMapEntry( StringToStringMap* map, String key );
