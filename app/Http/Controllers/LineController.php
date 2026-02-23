<?php

namespace App\Http\Controllers;

use App\Models\Line;
use Illuminate\Http\Request;

class LineController extends Controller
{
    public function index()
    {
        $lines = Line::query()->orderBy('name')->paginate(20);
        return view('lines.index', compact('lines'));
    }

    public function create()
    {
        return view('lines.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:lines,code'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool)($data['is_active'] ?? false);

        Line::create($data);
        return redirect()->route('lines.index')->with('status', 'Line created.');
    }

    public function edit(Line $line)
    {
        return view('lines.edit', compact('line'));
    }

    public function update(Request $request, Line $line)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:lines,code,' . $line->id],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool)($data['is_active'] ?? false);

        $line->update($data);
        return redirect()->route('lines.index')->with('status', 'Line updated.');
    }

    public function destroy(Line $line)
    {
        $line->delete();
        return redirect()->route('lines.index')->with('status', 'Line deleted.');
    }
}
