<?php

use App\Actions\TextToArray;

it('it converts key/value strings to array by semicolon and new-line', function ($actual, $expected) {
    expect(
        TextToArray::run(
            $actual['content'],
        )
    )
        ->toBe($expected);
})
    ->with([
        [
            'actual' => [
                'content' => "GOOGLE_API=MY_API_KEY;APP_KEY=ba=)(*&^%$#@!se64:psYUiUHUTds+8TLxgsUqAw=",
            ],
            'expected' => [
                'GOOGLE_API' => 'MY_API_KEY',
                'APP_KEY' => 'ba=)(*&^%$#@!se64:psYUiUHUTds+8TLxgsUqAw='
            ],
        ],
        [
            'actual' => [
                'content' => "GOOGLE_API=MY_API_KEY\nGITHUB_API=MY_SECOND_KEY",
            ],
            'expected' => [
                'GOOGLE_API' => 'MY_API_KEY',
                'GITHUB_API' => 'MY_SECOND_KEY'
            ],
        ],
        [
            'actual' => [
                'content' => "GOOGLE_API=MY_API_KEY\nINVALID_COMMENT INVALID_VALUE\nGITHUB_API=MY_SECOND_KEY",
            ],
            'expected' => [],
        ],
    ]);
