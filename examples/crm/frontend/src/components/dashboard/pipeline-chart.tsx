'use client';

import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { formatCurrency } from '@/lib/utils';

interface PipelineStage {
  stage: string;
  label: string;
  count: number;
  value: number;
  color: string;
}

interface PipelineChartProps {
  pipeline: PipelineStage[];
}

export function PipelineChart({ pipeline }: PipelineChartProps) {
  const maxValue = Math.max(...pipeline.map((s) => s.value));

  return (
    <Card>
      <CardHeader>
        <CardTitle>Pipeline Overview</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="space-y-4">
          {pipeline.map((stage) => (
            <div key={stage.stage}>
              <div className="mb-1.5 flex items-center justify-between text-sm">
                <div className="flex items-center gap-2">
                  <span className="font-medium text-slate-700">{stage.label}</span>
                  <span className="text-slate-400">({stage.count} deals)</span>
                </div>
                <span className="font-semibold text-slate-900">{formatCurrency(stage.value)}</span>
              </div>
              <div className="h-3 w-full overflow-hidden rounded-full bg-slate-100">
                <div
                  className={`h-full rounded-full transition-all duration-500 ${stage.color}`}
                  style={{ width: `${(stage.value / maxValue) * 100}%` }}
                />
              </div>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}
