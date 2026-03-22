import { useState } from 'react';
import {
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
} from '@nextui-org/react';
import { useEntries } from '@/api/entries';
import EntryTable from '@/components/EntryTable';
import type { ColumnDef } from '@/components/EntryTable';
import DurationBadge from '@/components/DurationBadge';
import type { BaseEntry, CacheData } from '@/schemas/entry';

const operationColors: Record<string, 'success' | 'danger' | 'primary' | 'default'> = {
  hit: 'success',
  miss: 'danger',
  write: 'primary',
  forget: 'default',
};

export default function CachePage() {
  const { data, isLoading } = useEntries('cache');
  const [selected, setSelected] = useState<BaseEntry | null>(null);

  const cData = (entry: BaseEntry): CacheData =>
    entry.data as unknown as CacheData;

  const columns: ColumnDef[] = [
    {
      key: 'operation',
      label: 'Operation',
      width: 100,
      render: (item) => (
        <Chip
          size="sm"
          color={operationColors[cData(item).operation] ?? 'default'}
          variant="flat"
        >
          {cData(item).operation.toUpperCase()}
        </Chip>
      ),
    },
    {
      key: 'key',
      label: 'Key',
      render: (item) => (
        <span className="font-mono text-sm truncate max-w-md block">
          {cData(item).key}
        </span>
      ),
    },
    {
      key: 'store',
      label: 'Store',
      width: 100,
      render: (item) => (
        <span className="text-xs text-default-500">{cData(item).store}</span>
      ),
    },
    {
      key: 'duration',
      label: 'Duration',
      width: 90,
      render: (item) => <DurationBadge ms={cData(item).duration_ms} />,
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

  const detail = selected ? cData(selected) : null;

  return (
    <div>
      <h1 className="text-xl font-bold mb-4">Cache</h1>

      <EntryTable
        data={data}
        columns={columns}
        isLoading={isLoading}
        onRowClick={setSelected}
        searchPlaceholder="Filter by key..."
      />

      <Modal
        isOpen={!!selected}
        onClose={() => setSelected(null)}
        size="2xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          {() => (
            <>
              <ModalHeader className="gap-3">
                Cache Operation
                {detail && (
                  <Chip
                    size="sm"
                    color={operationColors[detail.operation] ?? 'default'}
                    variant="flat"
                  >
                    {detail.operation.toUpperCase()}
                  </Chip>
                )}
              </ModalHeader>
              <ModalBody>
                {detail && (
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <p className="text-xs text-default-400">Key</p>
                      <p className="text-sm font-mono break-all">{detail.key}</p>
                    </div>
                    <div>
                      <p className="text-xs text-default-400">Store</p>
                      <p className="text-sm">{detail.store}</p>
                    </div>
                    <div>
                      <p className="text-xs text-default-400">Duration</p>
                      <DurationBadge ms={detail.duration_ms} />
                    </div>
                    <div>
                      <p className="text-xs text-default-400">TTL</p>
                      <p className="text-sm">
                        {detail.ttl != null ? `${detail.ttl}s` : 'N/A'}
                      </p>
                    </div>
                    <div>
                      <p className="text-xs text-default-400">Value Size</p>
                      <p className="text-sm">
                        {detail.value_size != null
                          ? `${detail.value_size} bytes`
                          : 'N/A'}
                      </p>
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
