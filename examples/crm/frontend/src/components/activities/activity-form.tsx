'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { useToast } from '@/components/ui/toast';

interface ActivityFormProps {
  open: boolean;
  onClose: () => void;
}

export function ActivityForm({ open, onClose }: ActivityFormProps) {
  const { toast } = useToast();
  const [formData, setFormData] = useState({
    type: 'task',
    title: '',
    description: '',
    due_date: '',
    priority: 'medium',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    toast({ type: 'success', title: 'Activity created', description: formData.title });
    setFormData({ type: 'task', title: '', description: '', due_date: '', priority: 'medium' });
    onClose();
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent onClose={onClose}>
        <DialogHeader>
          <DialogTitle>New Activity</DialogTitle>
        </DialogHeader>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="act-type">Type</Label>
              <Select
                id="act-type"
                value={formData.type}
                onChange={(e) => setFormData({ ...formData, type: e.target.value })}
              >
                <option value="task">Task</option>
                <option value="call">Call</option>
                <option value="meeting">Meeting</option>
                <option value="email">Email</option>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="act-priority">Priority</Label>
              <Select
                id="act-priority"
                value={formData.priority}
                onChange={(e) => setFormData({ ...formData, priority: e.target.value })}
              >
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
              </Select>
            </div>
          </div>
          <div className="space-y-2">
            <Label htmlFor="act-title">Title</Label>
            <Input
              id="act-title"
              value={formData.title}
              onChange={(e) => setFormData({ ...formData, title: e.target.value })}
              placeholder="Follow up call with..."
              required
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="act-desc">Description</Label>
            <Textarea
              id="act-desc"
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              placeholder="Activity details..."
              rows={3}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="act-due">Due Date</Label>
            <Input
              id="act-due"
              type="datetime-local"
              value={formData.due_date}
              onChange={(e) => setFormData({ ...formData, due_date: e.target.value })}
              required
            />
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
            <Button type="submit">Create Activity</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
