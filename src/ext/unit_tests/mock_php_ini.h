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

//#include "StringToStringMap.h"

//void setMockPhpIniInitialContent( StringToStringMap* mapAsCustomPhpIni );
//void deleteMockPhpIni();
//void setMockPhpIniEntry( String key, String value );
//String getMockPhpIniEntry( String key, bool* exists );
//void deleteMockPhpIniEntry( String key );

void initMockPhpIni();
//void setMockPhpIni( const StringToStringMap* mockPhpIni );
//void resetMockPhpIni();
void uninitMockPhpIni();
