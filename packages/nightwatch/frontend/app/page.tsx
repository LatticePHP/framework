"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useModeStore } from "@/lib/store";
import { Loader2 } from "lucide-react";

export default function RootPage() {
  const router = useRouter();
  const mode = useModeStore((s) => s.mode);

  useEffect(() => {
    if (mode === "prod") {
      router.replace("/prod/overview");
    } else {
      router.replace("/dev/requests");
    }
  }, [mode, router]);

  return (
    <div className="flex items-center justify-center h-64">
      <div className="flex items-center gap-2 text-muted-foreground">
        <Loader2 className="h-5 w-5 animate-spin" />
        <span>Initializing Nightwatch...</span>
      </div>
    </div>
  );
}
