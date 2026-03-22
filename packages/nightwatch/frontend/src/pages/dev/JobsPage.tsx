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
import DurationBadge from '@/components/DurationBadge';
import type { BaseEntry, JobData } from '@/schemas/entry';

const statusColors: Record<string, 'success' | 'warning' | 'danger' | 'primary' | 'default'> = {
  completed: 'success',
  processed: 'success',
  queued: 'primary',
  processing: 'warning',
  failed: 'danger',
  retrying: 'warning',
};

export default function JobsPage() {
  const { data, isLoading } = useEntries('job');
  const [selected, setSelected] = useState<BaseEntry | null>(null);

  const jData = (entry: BaseEntry): JobData =>
    entry.data as unknown as JobData;

  const shortClass = (cls: string) => {
    const parts = cls.split('\\');
    return parts[parts.length - 1] ?? cls;
  };

  const columns: ColumnDef[] = [
    {
      key: 'job_class',
      label: 'Job',
      render: (item) => (
        <div>
          <span className="text-sm font-semibold">
            {shortClass(jData(item).job_class)}
          </span>
          <p className="text-xs text-default-400 font-mono truncate max-w-md">
            {jData(item).job_class}
          </p>
        </div>
      ),
    },
    {
      key: 'queue',
      label: 'Queue',
      width: 100,
      render: (item) => (
        <Chip size="sm" variant="flat">
          {jData(item).queue}
        </Chip>
      ),
    },
    {
      key: 'status',
      label: 'Status',
      width: 110,
      render: (item) => (
        <Chip
          size="sm"
          color={statusColors[jData(item).status] ?? 'default'}
          variant="flat"
        >
          {jData(item).status}
        </Chip>
      ),
    },
    {
      key: 'duration',
      label: 'Duration',
      width: 90,
      render: (item) =>
        jData(item).duration_ms != null ? (
          <DurationBadge ms={jData(item).duration_ms!} />
        ) : (
          <span className="text-xs text-default-400">--</span>
        ),
    },
    {
      key: 'attempt',
      label: 'Attempt',
      width: 80,
      render: (item) => (
        <span className="text-xs">
          {jData(item).attempt}
          {jData(item).max_tries != null ? `/${jData(item).max_tries}` : ''}
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

  const detail = selected ? jData(selected) : null;

  return (
    <div>
      <h1 className="text-xl font-bold mb-4">Jobs</h1>

      <EntryTable
        data={data}
        columns={columns}
        isLoading={isLoading}
        onRowClick={setSelected}
        searchPlaceholder="Filter by job class..."
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
                {detail && shortClass(detail.job_class)}
              </ModalHeader>
              <ModalBody>
                {detail && (
                  <div className="space-y-4">
                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                      <div>
                        <p className="text-xs text-default-400">Full Class</p>
                        <p className="text-xs font-mono">{detail.job_class}</p>
                      </div>
                      <div>
                        <p className="text-xs text-default-400">Queue</p>
                        <p className="text-sm">{detail.queue}</p>
                      </div>
                      <div>
                        <p className="text-xs text-default-400">Connection</p>
                        <p className="text-sm">{detail.connection}</p>
                      </div>
                      <div>
                        <p className="text-xs text-default-400">Status</p>
                        <Chip
                          size="sm"
                          color={statusColors[detail.status] ?? 'default'}
                          variant="flat"
                        >
                          {detail.status}
                        </Chip>
                      </div>
                      <div>
                        <p className="text-xs text-default-400">Attempt</p>
                        <p className="text-sm">
                          {detail.attempt}
                          {detail.max_tries != null ? ` / ${detail.max_tries}` : ''}
                        </p>
                      </div>
                      <div>
                        <p className="text-xs text-default-400">Duration</p>
                        {detail.duration_ms != null ? (
                          <DurationBadge ms={detail.duration_ms} />
                        ) : (
                          <span className="text-sm text-default-400">N/A</span>
                        )}
                      </div>
                    </div>

                    {detail.payload && Object.keys(detail.payload).length > 0 && (
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

                    {detail.exception && (
                      <>
                        <Divider />
                        <div>
                          <p className="text-sm font-semibold mb-2 text-danger">
                            Exception
                          </p>
                          <p className="text-sm bg-danger-50 dark:bg-danger-50/10 rounded-lg p-3 text-danger font-mono">
                            {detail.exception}
                          </p>
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
