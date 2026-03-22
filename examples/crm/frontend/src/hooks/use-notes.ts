'use client';

import { useState, useEffect, useCallback } from 'react';
import type { Note } from '@/lib/types';

const DEMO_NOTES: Note[] = [
  { id: 1, body: 'Had a great initial call. Alex is very interested in our enterprise features, especially the workflow engine and the ability to run durable executions without external infrastructure.', notable_type: 'contact', notable_id: 1, author_id: 1, author: { id: 1, name: 'Sarah Chen', email: 'sarah@lattice.dev', role: 'admin' }, created_at: '2024-03-20T10:00:00Z' },
  { id: 2, body: 'Maria mentioned they are evaluating 3 vendors. We need to differentiate on developer experience and modular architecture.', notable_type: 'contact', notable_id: 2, author_id: 1, author: { id: 1, name: 'Sarah Chen', email: 'sarah@lattice.dev', role: 'admin' }, created_at: '2024-03-18T14:00:00Z' },
  { id: 3, body: 'James wants a proof of concept by end of month. Budget is tight but there is potential for expansion.', notable_type: 'contact', notable_id: 3, author_id: 1, author: { id: 1, name: 'Sarah Chen', email: 'sarah@lattice.dev', role: 'admin' }, created_at: '2024-03-15T09:00:00Z' },
  { id: 4, body: 'Pricing discussion went well. They are comparing us against a Temporal Cloud setup, and our zero-infra story is compelling.', notable_type: 'deal', notable_id: 1, author_id: 1, author: { id: 1, name: 'Sarah Chen', email: 'sarah@lattice.dev', role: 'admin' }, created_at: '2024-03-19T16:00:00Z' },
  { id: 5, body: 'Emily confirmed budget approval for the initial phase. Moving to SOW preparation.', notable_type: 'contact', notable_id: 4, author_id: 1, author: { id: 1, name: 'Sarah Chen', email: 'sarah@lattice.dev', role: 'admin' }, created_at: '2024-03-21T11:00:00Z' },
];

export function useNotes(notableType: string, notableId: number) {
  const [notes, setNotes] = useState<Note[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchNotes = useCallback(() => {
    setLoading(true);
    setTimeout(() => {
      const filtered = DEMO_NOTES.filter(
        (n) => n.notable_type === notableType && n.notable_id === notableId,
      );
      setNotes(filtered);
      setLoading(false);
    }, 200);
  }, [notableType, notableId]);

  useEffect(() => {
    fetchNotes();
  }, [fetchNotes]);

  const addNote = useCallback(
    (body: string) => {
      const newNote: Note = {
        id: Date.now(),
        body,
        notable_type: notableType,
        notable_id: notableId,
        author_id: 1,
        author: { id: 1, name: 'Sarah Chen', email: 'sarah@lattice.dev', role: 'admin' },
        created_at: new Date().toISOString(),
      };
      setNotes((prev) => [newNote, ...prev]);
    },
    [notableType, notableId],
  );

  return { notes, loading, addNote, refetch: fetchNotes };
}
