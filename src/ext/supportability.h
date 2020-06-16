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

#include "basic_types.h" // String
#include "TextOutputStream.h" // String

struct StructuredTextPrinter;
typedef struct StructuredTextPrinter StructuredTextPrinter;

struct StructuredTextPrinter
{
    void (* printSectionHeading ) ( StructuredTextPrinter* structTxtPrinter, String heading );
    void (* printTableBegin ) ( StructuredTextPrinter* structTxtPrinter, size_t numberOfColumns );
    void (* printTableHeader ) ( StructuredTextPrinter* structTxtPrinter, size_t numberOfColumns, String columnHeaders[] );
    void (* printTableRow ) ( StructuredTextPrinter* structTxtPrinter, size_t numberOfColumns, String columns[] );
    void (* printTableEnd ) ( StructuredTextPrinter* structTxtPrinter, size_t numberOfColumns );
};
typedef struct StructuredTextPrinter StructuredTextPrinter;

struct StructuredTextToOutputStreamPrinter
{
    // base must be the first field so StructuredTextToOutputStreamPrinter* can be cast from/to StructuredTextPrinter*
    StructuredTextPrinter base;
    TextOutputStream* txtOutStream;
    StringView prefix;
};
typedef struct StructuredTextToOutputStreamPrinter StructuredTextToOutputStreamPrinter;

void initStructuredTextToOutputStreamPrinter(
        /* in */ TextOutputStream* txtOutStream
        , StringView prefix
        , /* out */ StructuredTextToOutputStreamPrinter* structuredTextToOutputStreamPrinter );

void printSupportabilityInfo( StructuredTextPrinter* structTxtPrinter );
