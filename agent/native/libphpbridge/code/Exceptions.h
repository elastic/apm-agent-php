/*
 * Copyright Elasticsearch B.V. and/or licensed to Elasticsearch B.V. under one
 * or more contributor license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

#pragma once

#include <Zend/zend_compile.h>
#include <Zend/zend_globals.h>
#include <Zend/zend_types.h>
#include <optional>

namespace elasticapm::php
{

struct SavedException {
    zend_object* exception = nullptr;
    zend_object* prev_exception = nullptr;
    const zend_op* opline_before_exception = nullptr;
    std::optional< const zend_op* > opline;
};

SavedException saveExceptionState();
void restoreExceptionState( SavedException savedException );


class AutomaticExceptionStateRestorer
{
public:
    AutomaticExceptionStateRestorer()
        : savedException( saveExceptionState() )
    {
    }
    ~AutomaticExceptionStateRestorer()
    {
        restoreExceptionState( savedException );
    }
    auto getException()
    {
        return savedException.exception;
    }

private:
    SavedException savedException;
};

}// namespace elasticapm::php
