<?php

namespace Plugins\G7\Plugin\Custom\BoardComments\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Plugins\G7\Plugin\Custom\BoardComments\Support\SettingsStore;

class SettingsController extends Controller
{
    public function show()
    {
        return response()->json(SettingsStore::get());
    }

    public function save(Request $request)
    {
        $payload = $request->all();
        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload = $payload['data'];
        }
        if (isset($payload['settings']) && is_array($payload['settings'])) {
            $payload = $payload['settings'];
        }
        if (isset($payload['form']) && is_array($payload['form'])) {
            $payload = $payload['form'];
        }

        return response()->json(SettingsStore::put($payload));
    }
}
