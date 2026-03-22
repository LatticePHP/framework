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
  Select,
  SelectItem,
} from '@nextui-org/react';
import { useEntries } from '@/api/entries';
import EntryTable from '@/components/EntryTable';
import type { ColumnDef } from '@/components/EntryTable';
import StatusCode from '@/components/StatusCode';
import DurationBadge from '@/components/DurationBadge';
import type { BaseEntry, RequestData } from '@/schemas/entry';
import { useFiltersStore } from '@/stores/filters';

export default function RequestsPage() {
  const { data, isLoading } = useEntries('request');
  const [selected, setSelected] = useState<BaseEntry | null>(null);
  const { methodFilter, setMethodFilter, statusFilter, setStatusFilter } = useFiltersStore();

  const reqData = (entry: BaseEntry): RequestData => entry.data as unknown as RequestData;

  const columns: ColumnDef[] = [
    {
      key: 'method',
      label: 'Method',
      width: 80,
      render: (item) => (
        <Chip
          size="sm"
          variant="flat"
          color={
            reqData(item).method === 'GET'
              ? 'primary'
              : reqData(item).method === 'POST'
                ? 'success'
                : reqData(item).method === 'DELETE'
                  ? 'danger'
                  : 'warning'
          }
        >
          {reqData(item).method}
        </Chip>
      ),
    },
    {
      key: 'uri',
      label: 'URI',
      render: (item) => (
        <span className="font-mono text-sm truncate max-w-md block">
          {reqData(item).uri}
        </span>
      ),
    },
    {
      key: 'status',
      label: 'Status',
      width: 80,
      render: (item) => <StatusCode status={reqData(item).status} />,
    },
    {
      key: 'duration',
      label: 'Duration',
      width: 100,
      render: (item) => <DurationBadge ms={reqData(item).duration_ms} />,
    },
    {
      key: 'timestamp',
      label: 'Time',
      width: 160,
      render: (item) => (
        <span className="text-xs text-default-400">
          {new Date(item.timestamp).toLocaleTimeString()}
        </span>
      ),
    },
  ];

  const detail = selected ? reqData(selected) : null;

  return (
    <div>
      <div className="flex flex-wrap gap-3 items-center mb-4">
        <h1 className="text-xl font-bold flex-1">Requests</h1>
        <Select
          size="sm"
          label="Method"
          selectedKeys={methodFilter ? [methodFilter] : []}
          onSelectionChange={(keys) => {
            const val = Array.from(keys)[0] as string | undefined;
            setMethodFilter(val ?? null);
          }}
          className="max-w-[120px]"
        >
          {['GET', 'POST', 'PUT', 'PATCH', 'DELETE'].map((m) => (
            <SelectItem key={m}>{m}</SelectItem>
          ))}
        </Select>
        <Select
          size="sm"
          label="Status"
          selectedKeys={statusFilter ? [String(statusFilter)] : []}
          onSelectionChange={(keys) => {
            const val = Array.from(keys)[0] as string | undefined;
            setStatusFilter(val ? Number(val) : null);
          }}
          className="max-w-[120px]"
        >
          {['200', '201', '301', '302', '400', '401', '403', '404', '422', '500', '503'].map(
            (s) => (
              <SelectItem key={s}>{s}</SelectItem>
            ),
          )}
        </Select>
      </div>

      <EntryTable
        data={data}
        columns={columns}
        isLoading={isLoading}
        onRowClick={setSelected}
        searchPlaceholder="Filter by URI..."
      />

      {/* Detail modal */}
      <Modal
        isOpen={!!selected}
        onClose={() => setSelected(null)}
        size="3xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          {() => (
            <>
              <ModalHeader className="flex gap-3 items-center">
                {detail && (
                  <>
                    <Chip size="sm" variant="flat" color="primary">
                      {detail.method}
                    </Chip>
                    <span className="font-mono text-sm">{detail.uri}</span>
                    <StatusCode status={detail.status} />
                  </>
                )}
              </ModalHeader>
              <ModalBody>
                {detail && (
                  <div className="space-y-4">
                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                      <InfoItem label="Duration" value={`${detail.duration_ms}ms`} />
                      <InfoItem label="Status" value={String(detail.status)} />
                      <InfoItem label="IP" value={detail.ip ?? 'N/A'} />
                      <InfoItem label="Controller" value={detail.controller ?? 'N/A'} />
                      <InfoItem label="Route" value={detail.route_name ?? 'N/A'} />
                      <InfoItem label="Response Size" value={detail.response_size ? `${detail.response_size} bytes` : 'N/A'} />
                      <InfoItem label="Content Type" value={detail.content_type ?? 'N/A'} />
                      <InfoItem label="User ID" value={detail.user_id != null ? String(detail.user_id) : 'Guest'} />
                    </div>

                    {detail.middleware && detail.middleware.length > 0 && (
                      <>
                        <Divider />
                        <div>
                          <p className="text-sm font-semibold mb-2">Middleware</p>
                          <div className="flex flex-wrap gap-1">
                            {detail.middleware.map((m, i) => (
                              <Chip key={i} size="sm" variant="flat">
                                {m}
                              </Chip>
                            ))}
                          </div>
                        </div>
                      </>
                    )}

                    {detail.headers && Object.keys(detail.headers).length > 0 && (
                      <>
                        <Divider />
                        <div>
                          <p className="text-sm font-semibold mb-2">Headers</p>
                          <div className="font-mono text-xs space-y-1 bg-content2 rounded-lg p-3 max-h-60 overflow-auto">
                            {Object.entries(detail.headers).map(([key, val]) => (
                              <div key={key}>
                                <span className="text-primary">{key}:</span>{' '}
                                <span className="text-default-500">{String(val)}</span>
                              </div>
                            ))}
                          </div>
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

function InfoItem({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs text-default-400">{label}</p>
      <p className="text-sm font-mono truncate">{value}</p>
    </div>
  );
}
