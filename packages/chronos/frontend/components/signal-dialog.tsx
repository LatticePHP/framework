"use client";

import { useState, useCallback } from "react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { useSignalWorkflow } from "@/lib/api";
import { cn } from "@/lib/utils";
import { CheckCircle2, XCircle, Loader2 } from "lucide-react";

const COMMON_SIGNALS = [
  { key: "approve", label: "approve" },
  { key: "reject", label: "reject" },
  { key: "cancel", label: "cancel" },
  { key: "resume", label: "resume" },
  { key: "update", label: "update" },
  { key: "custom", label: "Custom..." },
];

interface SignalDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  workflowId: string;
}

export function SignalDialog({ open, onOpenChange, workflowId }: SignalDialogProps) {
  const [selectedSignal, setSelectedSignal] = useState("");
  const [customSignal, setCustomSignal] = useState("");
  const [payloadText, setPayloadText] = useState("");
  const [payloadError, setPayloadError] = useState("");
  const [step, setStep] = useState<"input" | "confirm" | "result">("input");

  const signalMutation = useSignalWorkflow();

  const signalName = selectedSignal === "custom" ? customSignal : selectedSignal;

  const validatePayload = useCallback((text: string): boolean => {
    if (text.trim() === "") {
      setPayloadError("");
      return true;
    }
    try {
      JSON.parse(text);
      setPayloadError("");
      return true;
    } catch {
      setPayloadError("Invalid JSON");
      return false;
    }
  }, []);

  const handleConfirm = () => {
    if (!signalName) return;
    if (!validatePayload(payloadText)) return;
    setStep("confirm");
  };

  const handleSend = async () => {
    const payload = payloadText.trim() ? JSON.parse(payloadText) : undefined;
    signalMutation.mutate(
      { id: workflowId, signal: signalName, payload },
      {
        onSuccess: () => setStep("result"),
        onError: () => setStep("result"),
      }
    );
  };

  const handleClose = () => {
    setSelectedSignal("");
    setCustomSignal("");
    setPayloadText("");
    setPayloadError("");
    setStep("input");
    signalMutation.reset();
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Send Signal</DialogTitle>
          <DialogDescription>
            Workflow: <span className="font-mono">{workflowId}</span>
          </DialogDescription>
        </DialogHeader>

        {step === "input" && (
          <div className="flex flex-col gap-4">
            <div className="flex flex-col gap-2">
              <Label htmlFor="signal-select">Signal Method</Label>
              <Select value={selectedSignal} onValueChange={setSelectedSignal}>
                <SelectTrigger id="signal-select">
                  <SelectValue placeholder="Select a signal" />
                </SelectTrigger>
                <SelectContent>
                  {COMMON_SIGNALS.map((s) => (
                    <SelectItem key={s.key} value={s.key}>
                      {s.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {selectedSignal === "custom" && (
              <div className="flex flex-col gap-2">
                <Label htmlFor="custom-signal">Custom Signal Name</Label>
                <Input
                  id="custom-signal"
                  placeholder="Enter signal method name"
                  value={customSignal}
                  onChange={(e) => setCustomSignal(e.target.value)}
                />
              </div>
            )}

            <div className="flex flex-col gap-2">
              <Label htmlFor="payload">Payload (JSON, optional)</Label>
              <Textarea
                id="payload"
                placeholder='{"key": "value"}'
                value={payloadText}
                onChange={(e) => {
                  setPayloadText(e.target.value);
                  validatePayload(e.target.value);
                }}
                className={cn("font-mono text-sm min-h-[120px]", payloadError && "border-destructive")}
              />
              {payloadError && (
                <p className="text-xs text-destructive">{payloadError}</p>
              )}
            </div>
          </div>
        )}

        {step === "confirm" && (
          <div className="flex flex-col gap-4">
            <p className="text-sm text-muted-foreground">
              Are you sure you want to send the following signal?
            </p>
            <div className="rounded-md bg-muted p-4">
              <div className="flex flex-col gap-2">
                <div>
                  <span className="text-xs text-muted-foreground">Signal:</span>
                  <p className="font-mono font-semibold">{signalName}</p>
                </div>
                {payloadText.trim() && (
                  <div>
                    <span className="text-xs text-muted-foreground">Payload:</span>
                    <pre className="text-sm font-mono mt-1 overflow-auto max-h-32">
                      {JSON.stringify(JSON.parse(payloadText), null, 2)}
                    </pre>
                  </div>
                )}
              </div>
            </div>
          </div>
        )}

        {step === "result" && (
          <div className="flex flex-col gap-3">
            {signalMutation.isSuccess && (
              <div className="flex items-start gap-3 rounded-md border border-emerald-500/30 bg-emerald-500/10 p-4">
                <CheckCircle2 className="h-5 w-5 text-emerald-500 mt-0.5 shrink-0" />
                <div>
                  <p className="font-semibold text-emerald-700 dark:text-emerald-400">
                    Signal delivered successfully
                  </p>
                  <p className="text-sm text-muted-foreground mt-1">
                    Signal &quot;{signalName}&quot; was sent to the workflow.
                  </p>
                </div>
              </div>
            )}
            {signalMutation.isError && (
              <div className="flex items-start gap-3 rounded-md border border-destructive/30 bg-destructive/10 p-4">
                <XCircle className="h-5 w-5 text-destructive mt-0.5 shrink-0" />
                <div>
                  <p className="font-semibold text-destructive">Failed to send signal</p>
                  <p className="text-sm text-muted-foreground mt-1">
                    {signalMutation.error instanceof Error
                      ? signalMutation.error.message
                      : "Unknown error"}
                  </p>
                </div>
              </div>
            )}
          </div>
        )}

        <DialogFooter>
          {step === "input" && (
            <>
              <Button variant="outline" onClick={handleClose}>
                Cancel
              </Button>
              <Button onClick={handleConfirm} disabled={!signalName}>
                Review
              </Button>
            </>
          )}
          {step === "confirm" && (
            <>
              <Button variant="outline" onClick={() => setStep("input")}>
                Back
              </Button>
              <Button
                onClick={() => void handleSend()}
                disabled={signalMutation.isPending}
              >
                {signalMutation.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
                Send Signal
              </Button>
            </>
          )}
          {step === "result" && (
            <Button onClick={handleClose}>Done</Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
