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
import type { BaseEntry, EventData } from '@/schemas/entry';

export default function EventsPage() {
  const { data, isLoading } = useEntries('event');
  const [selected, setSelected] = useState<BaseEntry | null>(null);

  const evData = (entry: BaseEntry): EventData =>
    entry.data as unknown as EventData;

  const shortClass = (cls: string) => {
    const parts = cls.split('\\');
    return parts[parts.length - 1] ?? cls;
  };

  const columns: ColumnDef[] = [
    {
      key: 'event_class',
      label: 'Event',
      render: (item) => (
        <div>
          <span className="text-sm font-semibold">
            {shortClass(evData(item).event_class)}
          </span>
          <p className="text-xs text-default-400 font-mono truncate max-w-md">
            {evData(item).event_class}
          </p>
        </div>
      ),
    },
    {
      key: 'listeners',
      label: 'Listeners',
      width: 100,
      render: (item) => (
        <Chip size="sm" variant="flat">
          {evData(item).listeners.length}
        </Chip>
      ),
    },
    {
      key: 'broadcast',
      label: 'Broadcast',
      width: 100,
      render: (item) =>
        evData(item).broadcast ? (
          <Chip size="sm" color="secondary" variant="flat">
            Yes
          </Chip>
        ) : (
          <span className="text-xs text-default-400">No</span>
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

  const detail = selected ? evData(selected) : null;

  return (
    <div>
      <h1 className="text-xl font-bold mb-4">Events</h1>

      <EntryTable
        data={data}
        columns={columns}
        isLoading={isLoading}
        onRowClick={setSelected}
        searchPlaceholder="Filter by event class..."
      />

      <Modal
        isOpen={!!selected}
        onClose={() => setSelected(null)}
        size="3xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          {() => (
            <>
              <ModalHeader>
                {detail && shortClass(detail.event_class)}
              </ModalHeader>
              <ModalBody>
                {detail && (
                  <div className="space-y-4">
                    <div>
                      <p className="text-xs text-default-400">Full Class</p>
                      <p className="text-sm font-mono">{detail.event_class}</p>
                    </div>

                    <div className="flex gap-2">
                      <Chip size="sm" variant="flat">
                        {detail.listeners.length} listener(s)
                      </Chip>
                      {detail.broadcast && (
                        <Chip size="sm" color="secondary" variant="flat">
                          Broadcast
                        </Chip>
                      )}
                    </div>

                    {detail.listeners.length > 0 && (
                      <>
                        <Divider />
                        <div>
                          <p className="text-sm font-semibold mb-2">Listeners</p>
                          <div className="space-y-1">
                            {detail.listeners.map((listener, i) => (
                              <div
                                key={i}
                                className="text-xs font-mono bg-content2 rounded px-3 py-1.5"
                              >
                                {listener}
                              </div>
                            ))}
                          </div>
                        </div>
                      </>
                    )}

                    {detail.payload &&
                      Object.keys(detail.payload).length > 0 && (
                        <>
                          <Divider />
                          <div>
                            <p className="text-sm font-semibold mb-2">Payload</p>
                            <pre className="bg-content2 rounded-lg p-3 text-xs font-mono overflow-x-auto">
                              {JSON.stringify(detail.payload, null, 2)}
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
