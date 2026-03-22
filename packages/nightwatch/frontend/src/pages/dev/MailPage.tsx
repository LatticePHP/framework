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
import type { BaseEntry, MailData } from '@/schemas/entry';

export default function MailPage() {
  const { data, isLoading } = useEntries('mail');
  const [selected, setSelected] = useState<BaseEntry | null>(null);
  const [showPreview, setShowPreview] = useState(false);

  const mData = (entry: BaseEntry): MailData =>
    entry.data as unknown as MailData;

  const formatRecipients = (to: string | string[] | undefined): string => {
    if (!to) return 'N/A';
    if (Array.isArray(to)) return to.join(', ');
    return to;
  };

  const columns: ColumnDef[] = [
    {
      key: 'to',
      label: 'To',
      render: (item) => (
        <span className="text-sm truncate max-w-xs block">
          {formatRecipients(mData(item).to)}
        </span>
      ),
    },
    {
      key: 'subject',
      label: 'Subject',
      render: (item) => (
        <span className="text-sm font-semibold truncate max-w-md block">
          {mData(item).subject}
        </span>
      ),
    },
    {
      key: 'mailable',
      label: 'Mailable',
      width: 160,
      render: (item) => {
        const cls = mData(item).mailable_class;
        if (!cls) return <span className="text-xs text-default-400">N/A</span>;
        const parts = cls.split('\\');
        return (
          <span className="text-xs font-mono text-default-500">
            {parts[parts.length - 1]}
          </span>
        );
      },
    },
    {
      key: 'queued',
      label: 'Queued',
      width: 80,
      render: (item) =>
        mData(item).queued ? (
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

  const detail = selected ? mData(selected) : null;

  return (
    <div>
      <h1 className="text-xl font-bold mb-4">Mail</h1>

      <EntryTable
        data={data}
        columns={columns}
        isLoading={isLoading}
        onRowClick={setSelected}
        searchPlaceholder="Filter by subject or recipient..."
      />

      <Modal
        isOpen={!!selected}
        onClose={() => {
          setSelected(null);
          setShowPreview(false);
        }}
        size="3xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          {() => (
            <>
              <ModalHeader>{detail?.subject ?? 'Mail Detail'}</ModalHeader>
              <ModalBody>
                {detail && (
                  <div className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <p className="text-xs text-default-400">To</p>
                        <p className="text-sm">{formatRecipients(detail.to)}</p>
                      </div>
                      <div>
                        <p className="text-xs text-default-400">From</p>
                        <p className="text-sm">{detail.from ?? 'N/A'}</p>
                      </div>
                      {detail.cc && (
                        <div>
                          <p className="text-xs text-default-400">CC</p>
                          <p className="text-sm">{detail.cc.join(', ')}</p>
                        </div>
                      )}
                      {detail.bcc && (
                        <div>
                          <p className="text-xs text-default-400">BCC</p>
                          <p className="text-sm">{detail.bcc.join(', ')}</p>
                        </div>
                      )}
                      <div>
                        <p className="text-xs text-default-400">Mailable</p>
                        <p className="text-xs font-mono">
                          {detail.mailable_class ?? 'N/A'}
                        </p>
                      </div>
                      <div>
                        <p className="text-xs text-default-400">Queued</p>
                        <p className="text-sm">{detail.queued ? 'Yes' : 'No'}</p>
                      </div>
                    </div>

                    {detail.attachments && detail.attachments.length > 0 && (
                      <>
                        <Divider />
                        <div>
                          <p className="text-sm font-semibold mb-2">Attachments</p>
                          <div className="flex flex-wrap gap-1">
                            {detail.attachments.map((a, i) => (
                              <Chip key={i} size="sm" variant="flat">
                                {a}
                              </Chip>
                            ))}
                          </div>
                        </div>
                      </>
                    )}

                    <Divider />
                    <div>
                      <div className="flex items-center justify-between mb-2">
                        <p className="text-sm font-semibold">HTML Preview</p>
                        <Button
                          size="sm"
                          variant="flat"
                          onPress={() => setShowPreview(!showPreview)}
                        >
                          {showPreview ? 'Hide' : 'Show'} Preview
                        </Button>
                      </div>
                      {showPreview && selected && (
                        <div className="border border-divider rounded-lg overflow-hidden">
                          <iframe
                            src={`/nightwatch/api/mail/${selected.uuid}/html`}
                            title="Mail preview"
                            className="w-full h-96 bg-white"
                            sandbox="allow-same-origin"
                          />
                        </div>
                      )}
                    </div>
                  </div>
                )}
              </ModalBody>
              <ModalFooter>
                <Button
                  variant="light"
                  onPress={() => {
                    setSelected(null);
                    setShowPreview(false);
                  }}
                >
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
