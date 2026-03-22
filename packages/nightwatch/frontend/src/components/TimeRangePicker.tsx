import { Button, ButtonGroup } from '@nextui-org/react';
import type { TimePeriod } from '@/schemas/metrics';
import { useFiltersStore } from '@/stores/filters';

const TIME_RANGES: { label: string; value: TimePeriod }[] = [
  { label: '1h', value: '1h' },
  { label: '6h', value: '6h' },
  { label: '24h', value: '24h' },
  { label: '7d', value: '7d' },
  { label: '30d', value: '30d' },
];

export default function TimeRangePicker() {
  const { timeRange, setTimeRange } = useFiltersStore();

  return (
    <ButtonGroup size="sm" variant="flat">
      {TIME_RANGES.map((range) => (
        <Button
          key={range.value}
          color={timeRange === range.value ? 'primary' : 'default'}
          variant={timeRange === range.value ? 'solid' : 'flat'}
          onPress={() => setTimeRange(range.value)}
        >
          {range.label}
        </Button>
      ))}
    </ButtonGroup>
  );
}
