<?php

/*
Copyright 2014 - Nicolas Devenet <nicolas@devenet.info>

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

Code source hosted on https://github.com/nicolabricot/MoodPicker
*/

namespace Core;

final class Token {
    
    static function Generate() {
        $token = sha1(uniqid('', TRUE). '_' .mt_rand());
        $_SESSION['tokens'][$token] = 1;
        return $token;
    }

    static function Accept($token) {
        if (isset($_SESSION['tokens'][$token])) {
            unset($_SESSION['tokens'][$token]);
            return TRUE;
        }
        //writeLog('Invalid security token given', TRUE);
        return FALSE;
    }
    
}

?>