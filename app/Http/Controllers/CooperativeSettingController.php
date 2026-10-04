<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCooperativeSettingsRequest;
use App\Models\CooperativeSetting;
use Illuminate\Http\Request;

class CooperativeSettingController extends Controller
{
    public function show(Request $request)
    {
        abort_unless($request->user()->business_id, 403);

        return response()->json(['data' => $this->getSettings($request->user()->business_id)]);
    }

    public function update(UpdateCooperativeSettingsRequest $request)
    {
        $settings = $this->getSettings($request->user()->business_id);
        $settings->fill($request->validated())->save();

        return response()->json([
            'message' => 'Identitas Kopsis berhasil disimpan.',
            'data' => $settings->refresh(),
        ]);
    }

    private function getSettings(int $businessId): CooperativeSetting
    {
        return CooperativeSetting::firstOrCreate(
            ['business_id' => $businessId],
            ['cooperative_name' => 'Alz Point'],
        );
    }
}