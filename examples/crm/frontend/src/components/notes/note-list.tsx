'use client';

import { Avatar } from '@/components/ui/avatar';
import { formatRelativeDate } from '@/lib/utils';
import type { Note } from '@/lib/types';

interface NoteListProps {
  notes: Note[];
}

export function NoteList({ notes }: NoteListProps) {
  if (notes.length === 0) {
    return (
      <div className="py-8 text-center text-slate-400">
        <p>No notes yet. Add the first one.</p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {notes.map((note) => (
        <div key={note.id} className="flex gap-3">
          <Avatar fallback={note.author?.name || 'U'} size="sm" className="mt-0.5" />
          <div className="flex-1 rounded-lg bg-slate-50 p-3">
            <div className="flex items-center gap-2 mb-1">
              <span className="text-sm font-medium text-slate-700">
                {note.author?.name || 'Unknown'}
              </span>
              <span className="text-xs text-slate-400">
                {formatRelativeDate(note.created_at)}
              </span>
            </div>
            <p className="text-sm text-slate-600 whitespace-pre-wrap">{note.body}</p>
          </div>
        </div>
      ))}
    </div>
  );
}
