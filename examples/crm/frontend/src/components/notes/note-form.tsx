'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Send } from 'lucide-react';

interface NoteFormProps {
  onSubmit: (body: string) => void;
}

export function NoteForm({ onSubmit }: NoteFormProps) {
  const [body, setBody] = useState('');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!body.trim()) return;
    onSubmit(body.trim());
    setBody('');
  };

  return (
    <form onSubmit={handleSubmit} className="flex gap-2">
      <Textarea
        value={body}
        onChange={(e) => setBody(e.target.value)}
        placeholder="Add a note..."
        rows={2}
        className="flex-1"
      />
      <Button type="submit" size="icon" disabled={!body.trim()}>
        <Send className="h-4 w-4" />
      </Button>
    </form>
  );
}
