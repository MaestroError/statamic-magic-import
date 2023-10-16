<?php

namespace maestroerror\StatamicMagicImport\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use maestroerror\StatamicMagicImport\Helpers\JsonImporter;
use Statamic\Facades\Collection;
use Statamic\Facades\Blueprint;

class ImportController
{
    public function index()
    {
        return view('statamic-magic-import::upload');
    }

    public function upload(Request $request)
    {
        $stream = fopen($request->file('file'), 'r+');
        $contents = stream_get_contents($stream);
        fclose($stream);

        try {
            $prepared = $this->importer()->prepare($contents);
        } catch (\Exception $e) {
            return back()->withErrors($e->getMessage());
        }

        Cache::put('json-import.statamic.prepared', $prepared);

        return redirect()->to(cp_route('json-import.summary'));
    }

    public function summary()
    {
        if (!$data = Cache::get('json-import.statamic.prepared')) {
            return redirect()->to(cp_route('json-import.index'));
        }

        $collections = Collection::all()->toArray();
        $readyCollections = [];
        foreach ($collections as $coll) {
            $blueprint = $this->findTheBlueprint($coll['handle']);
            if ($blueprint) {
                $coll['handles_from_blueprint'] = $this->findValuesForKey($blueprint->contents(), "handle");
            } else {
                $coll['handles_from_blueprint'] = false;
            }
            $readyCollections[] = $coll;
        }

        return view('statamic-magic-import::summary', [
            'summary' => $this->importer()->summary($data),
            "collections" => $readyCollections
        ]);
    }

    public function import(Request $request)
    {
        $maxExecTime = ini_get('max_execution_time');
        set_time_limit(0);

        $prepared = Cache::get('json-import.statamic.prepared');

        $summary = $request->input('summary');
        $collectionPairs = $request->input('collectionPairs') ?? false;
        $collectionFieldPairs = $request->input('collectionFieldPairs') ?? false;

        // dd($summary, $collectionPairs, $collectionFieldPairs);

        $this->importer()->import($prepared, $summary, $collectionPairs, $collectionFieldPairs);

        set_time_limit($maxExecTime);

        return ['success' => true];
    }

    private function importer()
    {
        return new JsonImporter;
    }
    

    private function findValuesForKey($array, $key) {
        $results = [];
    
        if (is_array($array)) {
            if (isset($array[$key])) {
                $results[] = $array[$key];
            }
    
            foreach ($array as $subarray) {
                $results = array_merge($results, $this->findValuesForKey($subarray, $key));
            }
        }
    
        return $results;
    }

    private function findTheBlueprint($handle) {
        $blueprint = Blueprint::find($handle) ? Blueprint::find($handle) : Blueprint::find("collections/". $handle . "/" . $handle);
        if (!$blueprint) {
            // Sometimes it needs to remove plural "s", so the folder is "Pages" but blueprint named as "page"
            $blueprint = Blueprint::find("collections/". $handle . "/" . substr($handle, 0, -1));
        }
        if (!$blueprint) {
            $blueprint = Blueprint::find("collections/". $handle);
        }
        return $blueprint ?? false;
    }
}