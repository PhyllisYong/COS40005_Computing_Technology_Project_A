<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Inferences;
use App\Models\User;

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
                'score' => round($item['confidence'] * 100, 2),
                'classid' =>$item['class_index'] #for sample images, just solely the number 
            ];
        });

        return response()->json([
            'predictions' => $formatted
        ]);
    }
    
    public function heatmap(Request $request)
    {
        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }

        $file = $request->file('file');

        $response = Http::attach(
            'file',
            file_get_contents($file->getRealPath()),
            $file->getClientOriginalName()
        )->post('http://127.0.0.1:8001/heatmap');

        if (!$response->successful()) {
            return response()->json(['error' => 'Heatmap generation failed'], 500);
        }

        return response()->json([
            'heatmap' => $response->json()['heatmap']
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'predicted_label' => 'required|string',
            'confidence_score' => 'required|numeric'
        ]);

        $inference = Inferences::create([
            'predicted_label' => $request->predicted_label,
            'confidence_score' => $request->confidence_score,
            'user_id' =>User::inRandomOrder()->first()?->user_id,
        ]);

        return response()->json([
            'message' => 'Inference saved',
            'data' => $inference
        ]);
    }
}
