import { useState } from 'react';
import {
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Divider,
} from '@nextui-org/react';
import { useEntries } from '@/api/entries';
import EntryTable from '@/components/EntryTable';
import type { ColumnDef } from '@/components/EntryTable';
import StackTrace from '@/components/StackTrace';
import type { BaseEntry, ExceptionData, StackFrame } from '@/schemas/entry';

export default function ExceptionsPage() {
  const { data, isLoading } = useEntries('exception');
  const [selected, setSelected] = useState<BaseEntry | null>(null);

  const exData = (entry: BaseEntry): ExceptionData =>
    entry.data as unknown as ExceptionData;

  const shortClass = (cls: string) => {
    const parts = cls.split('\\');
    return parts[parts.length - 1] ?? cls;
  };

  const columns: ColumnDef[] = [
    {
      key: 'class',
      label: 'Exception',
      render: (item) => (
        <div>
          <span className="text-sm font-semibold text-danger">
            {shortClass(exData(item).class)}
          </span>
          <p className="text-xs text-default-400 font-mono truncate max-w-lg">
            {exData(item).class}
          </p>
        </div>
      ),
    },
    {
      key: 'message',
      label: 'Message',
      render: (item) => (
        <span className="text-sm truncate max-w-sm block">
          {exData(item).message}
        </span>
      ),
    },
    {
      key: 'location',
      label: 'Location',
      width: 200,
      render: (item) => (
        <span className="text-xs font-mono text-default-400 truncate block">
          {exData(item).file}:{exData(item).line}
        </span>
      ),
    },
    {
      key: 'timestamp',
      label: 'Time',
      width: 120,
      render: (item) => (
        <span className="text-xs text-default-400">
          {new Date(item.timestamp).toLocaleTimeString()}
        </span>
      ),
    },
  ];

  const detail = selected ? exData(selected) : null;

  return (
    <div>
      <div className="flex flex-wrap gap-3 items-center mb-4">
        <h1 className="text-xl font-bold">Exceptions</h1>
      </div>

      <EntryTable
        data={data}
        columns={columns}
        isLoading={isLoading}
        onRowClick={setSelected}
        searchPlaceholder="Filter by class or message..."
      />

      <Modal
        isOpen={!!selected}
        onClose={() => setSelected(null)}
        size="4xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          {() => (
            <>
              <ModalHeader className="flex-col items-start">
                {detail && (
                  <>
                    <Chip size="sm" color="danger" variant="flat">
                      {shortClass(detail.class)}
                    </Chip>
                    <p className="text-sm font-mono text-default-400 mt-1">
                      {detail.class}
                    </p>
                  </>
                )}
              </ModalHeader>
              <ModalBody>
                {detail && (
                  <div className="space-y-4">
                    <div>
                      <p className="text-sm font-semibold mb-1">Message</p>
                      <p className="text-sm bg-danger-50 dark:bg-danger-50/10 rounded-lg p-3 text-danger">
                        {detail.message}
                      </p>
                    </div>

                    <div className="grid grid-cols-3 gap-4">
                      <div>
                        <p className="text-xs text-default-400">Code</p>
                        <p className="text-sm">{detail.code ?? 'N/A'}</p>
                      </div>
                      <div>
                        <p className="text-xs text-default-400">File</p>
                        <p className="text-xs font-mono truncate">{detail.file}</p>
                      </div>
                      <div>
                        <p className="text-xs text-default-400">Line</p>
                        <p className="text-sm">{detail.line}</p>
                      </div>
                    </div>

                    {detail.trace && detail.trace.length > 0 && (
                      <>
                        <Divider />
                        <div>
                          <p className="text-sm font-semibold mb-2">Stack Trace</p>
                          <div className="bg-content2 rounded-lg p-3 overflow-x-auto">
                            <StackTrace frames={detail.trace as StackFrame[]} />
                          </div>
                        </div>
                      </>
                    )}

                    {detail.previous && (
                      <>
                        <Divider />
                        <div>
                          <p className="text-sm font-semibold mb-2">Previous Exception</p>
                          <div className="bg-content2 rounded-lg p-3 text-xs font-mono">
                            <p className="text-danger">
                              {String((detail.previous as Record<string, unknown>).class)}
                            </p>
                            <p className="text-default-500 mt-1">
                              {String((detail.previous as Record<string, unknown>).message)}
                            </p>
                          </div>
                        </div>
                      </>
                    )}

                    {detail.request_context &&
                      Object.keys(detail.request_context).length > 0 && (
                        <>
                          <Divider />
                          <div>
                            <p className="text-sm font-semibold mb-2">Request Context</p>
                            <pre className="bg-content2 rounded-lg p-3 text-xs font-mono overflow-x-auto">
                              {JSON.stringify(detail.request_context, null, 2)}
                            </pre>
                          </div>
                        </>
                      )}
                  </div>
                )}
              </ModalBody>
              <ModalFooter>
                <Button variant="light" onPress={() => setSelected(null)}>
                  Close
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
