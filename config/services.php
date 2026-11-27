<?php return ['jwt'=>['secret'=>env('JWT_SECRET'),'days'=>(int)env('JWT_EXPIRES_DAYS',30)]];
