import { useState } from 'react';
import {
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Select,
  SelectItem,
} from '@nextui-org/react';
import { useEntries } from '@/api/entries';
import EntryTable from '@/components/EntryTable';
import type { ColumnDef } from '@/components/EntryTable';
import type { BaseEntry, LogData } from '@/schemas/entry';
import { useFiltersStore } from '@/stores/filters';

const levelColors: Record<string, 'success' | 'primary' | 'warning' | 'danger' | 'secondary' | 'default'> = {
  debug: 'default',
  info: 'primary',
  notice: 'secondary',
  warning: 'warning',
  error: 'danger',
  critical: 'danger',
  alert: 'danger',
  emergency: 'danger',
};

export default function LogsPage() {
  const { data, isLoading } = useEntries('log');
  const [selected, setSelected] = useState<BaseEntry | null>(null);
  const { levelFilter, setLevelFilter } = useFiltersStore();

  const lData = (entry: BaseEntry): LogData =>
    entry.data as unknown as LogData;

  const columns: ColumnDef[] = [
    {
      key: 'level',
      label: 'Level',
      width: 100,
      render: (item) => (
        <Chip
          size="sm"
          color={levelColors[lData(item).level] ?? 'default'}
          variant="flat"
        >
          {lData(item).level.toUpperCase()}
        </Chip>
      ),
    },
    {
      key: 'message',
      label: 'Message',
      render: (item) => (
        <span className="text-sm truncate max-w-2xl block">
          {lData(item).message}
        </span>
      ),
    },
    {
      key: 'channel',
      label: 'Channel',
      width: 100,
      render: (item) => (
        <span className="text-xs text-default-500">{lData(item).channel}</span>
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

  const detail = selected ? lData(selected) : null;

  return (
    <div>
      <div className="flex flex-wrap gap-3 items-center mb-4">
        <h1 className="text-xl font-bold flex-1">Logs</h1>
        <Select
          size="sm"
          label="Level"
          selectedKeys={levelFilter ? [levelFilter] : []}
          onSelectionChange={(keys) => {
            const val = Array.from(keys)[0] as string | undefined;
            setLevelFilter(val ?? null);
          }}
          className="max-w-[140px]"
        >
          {['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'].map(
            (l) => (
              <SelectItem key={l}>{l.toUpperCase()}</SelectItem>
            ),
          )}
        </Select>
      </div>

      <EntryTable
        data={data}
        columns={columns}
        isLoading={isLoading}
        onRowClick={setSelected}
        searchPlaceholder="Filter by message..."
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
              <ModalHeader className="gap-3">
                Log Entry
                {detail && (
                  <Chip
                    size="sm"
                    color={levelColors[detail.level] ?? 'default'}
                    variant="flat"
                  >
                    {detail.level.toUpperCase()}
                  </Chip>
                )}
              </ModalHeader>
              <ModalBody>
                {detail && (
                  <div className="space-y-4">
                    <div>
                      <p className="text-xs text-default-400">Message</p>
                      <p className="text-sm bg-content2 rounded-lg p-3 whitespace-pre-wrap">
                        {detail.message}
                      </p>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <p className="text-xs text-default-400">Channel</p>
                        <p className="text-sm">{detail.channel}</p>
                      </div>
                      <div>
                        <p className="text-xs text-default-400">Level</p>
                        <Chip
                          size="sm"
                          color={levelColors[detail.level] ?? 'default'}
                          variant="flat"
                        >
                          {detail.level.toUpperCase()}
                        </Chip>
                      </div>
                    </div>

                    {detail.context && Object.keys(detail.context).length > 0 && (
                      <div>
                        <p className="text-sm font-semibold mb-2">Context</p>
                        <pre className="bg-content2 rounded-lg p-3 text-xs font-mono overflow-x-auto">
                          {JSON.stringify(detail.context, null, 2)}
                        </pre>
                      </div>
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
