<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PredictController extends Controller
{

    public function identify(Request $request)
    {

        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }

        $file = $request->file('file');

        // Send file to FastAPI
        $response = Http::attach(
            'file',
            file_get_contents($file->getRealPath()),
            $file->getClientOriginalName()
        )->post('http://127.0.0.1:8001/predict');

        if (!$response->successful()) {
            return response()->json(['error' => 'AI service failed'], 500);
        }

        $aiResults = $response->json();

        // Transform for frontend
        $formatted = collect($aiResults)->map(function ($item) {
            return [
                'name' => $item['species'],
                'score' => round($item['confidence'] * 100, 2)
            ];
        });

        return response()->json([
            'predictions' => $formatted
        ]);

    }
    
}
