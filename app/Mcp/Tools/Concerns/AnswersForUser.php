<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\User;
use App\Support\MinorUnits;
use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * The shared shape of every read tool's answer (`.ai/rules/mcp.md`): the user from the request,
 * `app_day` first, and a display string beside every `*_minor_units` integer.
 */
trait AnswersForUser
{
    protected function userOf(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('No user is signed in to the MCP server.');
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $answer
     */
    protected function answer(CarbonImmutable $today, array $answer): ResponseFactory
    {
        return Response::structured([
            'app_day' => $today->toDateString(),
            ...$this->withDisplays($answer),
        ]);
    }

    /**
     * Adds `<name>_display` ("1234,56 zł") after every integer `<name>_minor_units`, at any depth.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function withDisplays(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->withDisplays($value);

                continue;
            }

            $result[$key] = $value;

            if (is_string($key) && is_int($value) && str_ends_with($key, '_minor_units')) {
                $result[substr($key, 0, -strlen('_minor_units')).'_display'] = MinorUnits::toMajorDisplay($value).' zł';
            }
        }

        return $result;
    }
}
