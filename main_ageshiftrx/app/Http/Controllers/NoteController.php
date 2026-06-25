<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NoteController extends Controller
{
    /**
     * Insert or Update Note
     */
    public function saveNote(Request $request)
    {
        $request->validate([
            'id'   => 'nullable|integer',
            'note' => 'required|string|max:255',
        ]);

        // If ID exists → UPDATE
        if ($request->id) {
            $exists = DB::table('notes')
                ->where('id', $request->id)
                ->exists();

            if ($exists) {
                DB::table('notes')
                    ->where('id', $request->id)
                    ->update([
                        'note' => $request->note,
                    ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Note updated successfully',
                    'id' => $request->id,
                ]);
            }
        }

        // Else → INSERT
        $id = DB::table('notes')->insertGetId([
            'note' => $request->note,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Note inserted successfully',
            'id' => $id,
        ]);
    }

    /**
     * Get Note (Single or All)
     */
    public function getNote($id = null)
    {
        if ($id) {
            $note = DB::table('notes')->where('id', $id)->first();

            if (!$note) {
                return response()->json([
                    'status' => false,
                    'message' => 'Note not found',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $note,
            ]);
        }

        // Get all notes
        $notes = DB::table('notes')->orderBy('id', 'desc')->get();

        return response()->json([
            'status' => true,
            'data' => $notes,
        ]);
    }



    public function updateLoginCount(Request $request)
    {
        $email = $request->email;

        if (!$email) {
            return response()->json([
                'status' => 0,
                'message' => 'Email is required'
            ]);
        }

        $user = DB::table('user')->where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'status' => 0,
                'message' => 'User not found'
            ]);
        }


        $loginCount = DB::table('user')
            ->where('email', $email)
            ->value('login_count');

        return response()->json([
            'status' => 1,
            'email' => $email,
            'login_count' => $loginCount
        ]);
    }


}

