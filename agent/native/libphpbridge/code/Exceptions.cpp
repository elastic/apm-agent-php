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

#include "Exceptions.h"
#include <Zend/zend_exceptions.h>

namespace elasticapm::php
{

SavedException saveExceptionState()
{
    SavedException savedException;
    savedException.exception = EG( exception );
    savedException.prev_exception = EG( prev_exception );
    savedException.opline_before_exception = EG( opline_before_exception );

    EG( exception ) = nullptr;
    EG( prev_exception ) = nullptr;
    EG( opline_before_exception ) = nullptr;

    if ( EG( current_execute_data ) )
    {
        savedException.opline = EG( current_execute_data )->opline;
    }
    return savedException;
}

void restoreExceptionState( SavedException savedException )
{
    EG( exception ) = savedException.exception;
    EG( prev_exception ) = savedException.prev_exception;
    EG( opline_before_exception ) = savedException.opline_before_exception;

    if ( EG( current_execute_data ) && savedException.opline.has_value() )
    {
        EG( current_execute_data )->opline = savedException.opline.value();
    }
}

}// namespace elasticapm::php
