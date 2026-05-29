<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\VoteSys\VoteSysAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function access(Request $request): VoteSysAccess
    {
        return VoteSysAccess::fromRequest($request);
    }

    protected function serviceMeta(): array
    {
        return [
            'service' => config('votesys.service_key'),
            'api_version' => config('votesys.api_version'),
        ];
    }

    protected function json(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(array_merge(['meta' => $this->serviceMeta()], is_array($data) ? $data : ['data' => $data]), $status);
    }
}
