"use client";

import { useModeStore } from "@/lib/store";
import { Switch } from "@/components/ui/switch";
import { Badge } from "@/components/ui/badge";

export default function ModeSwitcher() {
  const { mode, setMode } = useModeStore();

  return (
    <div className="flex items-center gap-3">
      <Badge variant={mode === "dev" ? "info" : "success"}>
        {mode === "dev" ? "DEV" : "PROD"}
      </Badge>
      <div className="flex items-center gap-2">
        <Switch
          checked={mode === "prod"}
          onCheckedChange={(isProd) => setMode(isProd ? "prod" : "dev")}
          id="mode-switch"
        />
        <label htmlFor="mode-switch" className="text-xs text-muted-foreground cursor-pointer">
          {mode === "dev" ? "Debug" : "Metrics"}
        </label>
      </div>
    </div>
  );
}
