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
  Switch,
} from '@nextui-org/react';
import { useEntries } from '@/api/entries';
import EntryTable from '@/components/EntryTable';
import type { ColumnDef } from '@/components/EntryTable';
import DurationBadge from '@/components/DurationBadge';
import SqlHighlight from '@/components/SqlHighlight';
import type { BaseEntry, QueryData } from '@/schemas/entry';
import { useFiltersStore } from '@/stores/filters';

export default function QueriesPage() {
  const { data, isLoading } = useEntries('query');
  const [selected, setSelected] = useState<BaseEntry | null>(null);
  const { slowOnly, setSlowOnly } = useFiltersStore();

  const qData = (entry: BaseEntry): QueryData => entry.data as unknown as QueryData;

  const columns: ColumnDef[] = [
    {
      key: 'sql',
      label: 'SQL',
      render: (item) => (
        <div className="max-w-lg">
          <SqlHighlight sql={qData(item).sql} truncate={120} />
        </div>
      ),
    },
    {
      key: 'duration',
      label: 'Duration',
      width: 100,
      render: (item) => <DurationBadge ms={qData(item).duration_ms} />,
    },
    {
      key: 'connection',
      label: 'Connection',
      width: 100,
      render: (item) => (
        <span className="text-xs text-default-500">{qData(item).connection}</span>
      ),
    },
    {
      key: 'badges',
      label: 'Flags',
      width: 120,
      render: (item) => (
        <div className="flex gap-1">
          {qData(item).slow && (
            <Chip size="sm" color="danger" variant="flat">
              SLOW
            </Chip>
          )}
          {qData(item).n1_detected && (
            <Chip size="sm" color="warning" variant="flat">
              N+1
            </Chip>
          )}
          <Chip size="sm" variant="flat">
            {qData(item).query_type}
          </Chip>
        </div>
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

  const detail = selected ? qData(selected) : null;

  return (
    <div>
      <div className="flex flex-wrap gap-3 items-center mb-4">
        <h1 className="text-xl font-bold flex-1">Queries</h1>
        <Switch size="sm" isSelected={slowOnly} onValueChange={setSlowOnly}>
          <span className="text-xs">Slow only</span>
        </Switch>
      </div>

      <EntryTable
        data={data}
        columns={columns}
        isLoading={isLoading}
        onRowClick={setSelected}
        searchPlaceholder="Filter by SQL..."
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
              <ModalHeader>Query Detail</ModalHeader>
              <ModalBody>
                {detail && (
                  <div className="space-y-4">
                    <div>
                      <p className="text-sm font-semibold mb-2">SQL</p>
                      <div className="bg-content2 rounded-lg p-4 overflow-x-auto">
                        <SqlHighlight sql={detail.sql} />
                      </div>
                    </div>

                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                      <div>
                        <p className="text-xs text-default-400">Duration</p>
                        <DurationBadge ms={detail.duration_ms} />
                      </div>
                      <div>
                        <p className="text-xs text-default-400">Connection</p>
                        <p className="text-sm">{detail.connection}</p>
                      </div>
                      <div>
                        <p className="text-xs text-default-400">Type</p>
                        <Chip size="sm" variant="flat">{detail.query_type}</Chip>
                      </div>
                      <div>
                        <p className="text-xs text-default-400">Caller</p>
                        <p className="text-xs font-mono text-default-500 truncate">
                          {detail.caller ?? 'N/A'}
                        </p>
                      </div>
                    </div>

                    {detail.bindings && detail.bindings.length > 0 && (
                      <>
                        <Divider />
                        <div>
                          <p className="text-sm font-semibold mb-2">Bindings</p>
                          <div className="bg-content2 rounded-lg p-3 font-mono text-xs">
                            {detail.bindings.map((b, i) => (
                              <div key={i}>
                                <span className="text-default-400">{i}:</span>{' '}
                                <span className="text-primary">{JSON.stringify(b)}</span>
                              </div>
                            ))}
                          </div>
                        </div>
                      </>
                    )}

                    <div className="flex gap-2">
                      {detail.slow && (
                        <Chip size="sm" color="danger" variant="flat">
                          SLOW
                        </Chip>
                      )}
                      {detail.n1_detected && (
                        <Chip size="sm" color="warning" variant="flat">
                          N+1 Detected
                        </Chip>
                      )}
                    </div>
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
