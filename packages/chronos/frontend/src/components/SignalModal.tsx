import { useState, useCallback } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Input,
  Select,
  SelectItem,
  Textarea,
} from '@nextui-org/react';
import { useSignalWorkflow } from '@/api/mutations';

interface SignalModalProps {
  isOpen: boolean;
  onClose: () => void;
  workflowId: string;
}

const COMMON_SIGNALS = [
  { key: 'approve', label: 'approve' },
  { key: 'reject', label: 'reject' },
  { key: 'cancel', label: 'cancel' },
  { key: 'resume', label: 'resume' },
  { key: 'update', label: 'update' },
  { key: 'custom', label: 'Custom...' },
];

export function SignalModal({ isOpen, onClose, workflowId }: SignalModalProps) {
  const [selectedSignal, setSelectedSignal] = useState('');
  const [customSignal, setCustomSignal] = useState('');
  const [payloadText, setPayloadText] = useState('');
  const [payloadError, setPayloadError] = useState('');
  const [step, setStep] = useState<'input' | 'confirm' | 'result'>('input');

  const signalMutation = useSignalWorkflow();

  const signalName = selectedSignal === 'custom' ? customSignal : selectedSignal;

  const validatePayload = useCallback(
    (text: string): boolean => {
      if (text.trim() === '') {
        setPayloadError('');
        return true;
      }
      try {
        JSON.parse(text);
        setPayloadError('');
        return true;
      } catch {
        setPayloadError('Invalid JSON');
        return false;
      }
    },
    [],
  );

  const handleConfirm = () => {
    if (!signalName) return;
    if (!validatePayload(payloadText)) return;
    setStep('confirm');
  };

  const handleSend = async () => {
    const payload = payloadText.trim() ? JSON.parse(payloadText) : undefined;
    signalMutation.mutate(
      { id: workflowId, signal: signalName, payload },
      {
        onSuccess: () => {
          setStep('result');
        },
        onError: () => {
          setStep('result');
        },
      },
    );
  };

  const handleClose = () => {
    setSelectedSignal('');
    setCustomSignal('');
    setPayloadText('');
    setPayloadError('');
    setStep('input');
    signalMutation.reset();
    onClose();
  };

  return (
    <Modal isOpen={isOpen} onClose={handleClose} size="lg">
      <ModalContent>
        <ModalHeader className="flex flex-col gap-1">
          Send Signal
          <span className="text-sm font-normal text-default-400">
            Workflow: {workflowId}
          </span>
        </ModalHeader>

        <ModalBody>
          {step === 'input' && (
            <>
              <Select
                label="Signal Method"
                placeholder="Select a signal"
                selectedKeys={selectedSignal ? [selectedSignal] : []}
                onSelectionChange={(keys) => {
                  const selected = Array.from(keys)[0];
                  if (typeof selected === 'string') setSelectedSignal(selected);
                }}
              >
                {COMMON_SIGNALS.map((s) => (
                  <SelectItem key={s.key}>{s.label}</SelectItem>
                ))}
              </Select>

              {selectedSignal === 'custom' && (
                <Input
                  label="Custom Signal Name"
                  placeholder="Enter signal method name"
                  value={customSignal}
                  onValueChange={setCustomSignal}
                />
              )}

              <Textarea
                label="Payload (JSON, optional)"
                placeholder='{"key": "value"}'
                value={payloadText}
                onValueChange={(val) => {
                  setPayloadText(val);
                  validatePayload(val);
                }}
                isInvalid={!!payloadError}
                errorMessage={payloadError}
                minRows={4}
                classNames={{
                  input: 'font-mono text-sm',
                }}
              />
            </>
          )}

          {step === 'confirm' && (
            <div className="space-y-4">
              <p className="text-default-600">
                Are you sure you want to send the following signal?
              </p>
              <div className="bg-default-100 rounded-lg p-4 space-y-2">
                <div>
                  <span className="text-xs text-default-400">Signal:</span>
                  <p className="font-mono font-semibold">{signalName}</p>
                </div>
                {payloadText.trim() && (
                  <div>
                    <span className="text-xs text-default-400">Payload:</span>
                    <pre className="text-sm font-mono mt-1 overflow-auto max-h-32">
                      {JSON.stringify(JSON.parse(payloadText), null, 2)}
                    </pre>
                  </div>
                )}
              </div>
            </div>
          )}

          {step === 'result' && (
            <div className="space-y-3">
              {signalMutation.isSuccess && (
                <div className="bg-success-50 dark:bg-success-100/10 border border-success-200 dark:border-success-500/30 rounded-lg p-4">
                  <p className="text-success font-semibold">Signal delivered successfully</p>
                  <p className="text-sm text-default-500 mt-1">
                    Signal &quot;{signalName}&quot; was sent to the workflow.
                  </p>
                </div>
              )}
              {signalMutation.isError && (
                <div className="bg-danger-50 dark:bg-danger-100/10 border border-danger-200 dark:border-danger-500/30 rounded-lg p-4">
                  <p className="text-danger font-semibold">Failed to send signal</p>
                  <p className="text-sm text-default-500 mt-1">
                    {signalMutation.error instanceof Error
                      ? signalMutation.error.message
                      : 'Unknown error'}
                  </p>
                </div>
              )}
            </div>
          )}
        </ModalBody>

        <ModalFooter>
          {step === 'input' && (
            <>
              <Button variant="light" onPress={handleClose}>
                Cancel
              </Button>
              <Button
                color="primary"
                onPress={handleConfirm}
                isDisabled={!signalName}
              >
                Review
              </Button>
            </>
          )}
          {step === 'confirm' && (
            <>
              <Button variant="light" onPress={() => setStep('input')}>
                Back
              </Button>
              <Button
                color="primary"
                onPress={() => void handleSend()}
                isLoading={signalMutation.isPending}
              >
                Send Signal
              </Button>
            </>
          )}
          {step === 'result' && (
            <Button color="primary" onPress={handleClose}>
              Done
            </Button>
          )}
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}
