<?php

namespace App\Http\Controllers;

use App\Http\Clients\FmbClearanceClient;
use Illuminate\Http\JsonResponse;

class ExternalFmbController extends Controller
{
    public function __construct(
        protected FmbClearanceClient $fmbClient
    ) {}

    public function show(string $its): JsonResponse
    {
        $cleared = $this->fmbClient->checkClearance($its);

        return $this->jsonSuccessWithData(['cleared' => $cleared]);
    }
}
