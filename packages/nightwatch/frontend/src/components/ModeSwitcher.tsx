import { Chip, Switch } from '@nextui-org/react';
import { useModeStore } from '@/stores/mode';

export default function ModeSwitcher() {
  const { mode, setMode } = useModeStore();

  return (
    <div className="flex items-center gap-3">
      <Chip
        color={mode === 'dev' ? 'secondary' : 'primary'}
        variant="flat"
        size="sm"
      >
        {mode === 'dev' ? 'DEV' : 'PROD'}
      </Chip>
      <Switch
        size="sm"
        isSelected={mode === 'prod'}
        onValueChange={(isProd) => setMode(isProd ? 'prod' : 'dev')}
        aria-label="Toggle dev/prod mode"
      >
        <span className="text-xs text-default-500">
          {mode === 'dev' ? 'Debug' : 'Metrics'}
        </span>
      </Switch>
    </div>
  );
}
