import { Card, CardBody } from '@nextui-org/react';

interface MetricCardProps {
  title: string;
  value: string | number;
  unit?: string;
  trend?: 'up' | 'down' | 'stable';
  changePercent?: number;
  color?: 'default' | 'primary' | 'success' | 'warning' | 'danger';
  onClick?: () => void;
}

function getTrendIcon(trend: 'up' | 'down' | 'stable'): string {
  switch (trend) {
    case 'up':
      return '\u2191';
    case 'down':
      return '\u2193';
    case 'stable':
      return '\u2192';
  }
}

function getTrendColor(trend: 'up' | 'down' | 'stable', isGoodUp = false): string {
  if (trend === 'stable') return 'text-default-400';
  if (trend === 'up') return isGoodUp ? 'text-success' : 'text-danger';
  return isGoodUp ? 'text-danger' : 'text-success';
}

export default function MetricCard({
  title,
  value,
  unit,
  trend,
  changePercent,
  color = 'default',
  onClick,
}: MetricCardProps) {
  const borderColorMap: Record<string, string> = {
    default: 'border-default-200',
    primary: 'border-primary-300',
    success: 'border-success-300',
    warning: 'border-warning-300',
    danger: 'border-danger-300',
  };

  return (
    <Card
      isPressable={!!onClick}
      onPress={onClick}
      className={`border-l-4 ${borderColorMap[color] ?? borderColorMap['default']}`}
      shadow="sm"
    >
      <CardBody className="p-4">
        <p className="text-xs text-default-500 uppercase tracking-wider mb-1">
          {title}
        </p>
        <div className="flex items-baseline gap-1.5">
          <span className="text-2xl font-bold text-foreground">
            {typeof value === 'number' ? value.toLocaleString() : value}
          </span>
          {unit && (
            <span className="text-sm text-default-400">{unit}</span>
          )}
        </div>
        {trend && (
          <div className={`flex items-center gap-1 mt-1 text-xs ${getTrendColor(trend)}`}>
            <span>{getTrendIcon(trend)}</span>
            {changePercent !== undefined && (
              <span>{Math.abs(changePercent).toFixed(1)}%</span>
            )}
          </div>
        )}
      </CardBody>
    </Card>
  );
}
