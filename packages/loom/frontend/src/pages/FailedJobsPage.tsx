import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Pagination,
  Input,
  Button,
  Skeleton,
  Spinner,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
} from '@nextui-org/react';
import { useFailedJobs } from '@/api/jobs';
import { useRetryJob, useRetryAll, useDeleteJob } from '@/api/mutations';
import { useFiltersStore } from '@/stores/filters';

function formatTime(iso: string | null | undefined): string {
  if (!iso) return '--';
  return new Date(iso).toLocaleString();
}

export default function FailedJobsPage() {
  const navigate = useNavigate();
  const [page, setPage] = useState(1);
  const [perPage] = useState(25);
  const [deleteTargetId, setDeleteTargetId] = useState<string | null>(null);

  const searchTerm = useFiltersStore((s) => s.searchTerm);
  const setSearchTerm = useFiltersStore((s) => s.setSearchTerm);

  const { data, isLoading, isFetching } = useFailedJobs(page, perPage);
  const retryJob = useRetryJob();
  const retryAll = useRetryAll();
  const deleteJob = useDeleteJob();

  const retryAllModal = useDisclosure();
  const deleteModal = useDisclosure();

  const handleRetry = (jobId: string, e: React.MouseEvent) => {
    e.stopPropagation();
    retryJob.mutate(jobId);
  };

  const handleDelete = (jobId: string, e: React.MouseEvent) => {
    e.stopPropagation();
    setDeleteTargetId(jobId);
    deleteModal.onOpen();
  };

  const confirmDelete = () => {
    if (deleteTargetId) {
      deleteJob.mutate(deleteTargetId);
    }
    deleteModal.onClose();
    setDeleteTargetId(null);
  };

  const confirmRetryAll = () => {
    retryAll.mutate();
    retryAllModal.onClose();
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold">Failed Jobs</h2>
        <div className="flex items-center gap-2">
          {isFetching && !isLoading && <Spinner size="sm" />}
          {data && data.data.length > 0 && (
            <Button
              size="sm"
              color="warning"
              variant="flat"
              onPress={retryAllModal.onOpen}
              isLoading={retryAll.isPending}
            >
              Retry All
            </Button>
          )}
        </div>
      </div>

      {/* Search */}
      <div className="flex flex-wrap gap-3">
        <Input
          size="sm"
          variant="bordered"
          placeholder="Search class or exception..."
          value={searchTerm}
          onValueChange={(val) => {
            setSearchTerm(val);
            setPage(1);
          }}
          className="w-80"
          isClearable
          onClear={() => {
            setSearchTerm('');
            setPage(1);
          }}
        />
      </div>

      {/* Table */}
      {isLoading ? (
        <div className="space-y-3">
          {Array.from({ length: 5 }).map((_, i) => (
            <Skeleton key={i} className="w-full h-12 rounded-lg" />
          ))}
        </div>
      ) : (
        <>
          <Table
            aria-label="Failed jobs table"
            selectionMode="single"
            onRowAction={(key) => navigate(`/jobs/${key}`)}
            classNames={{
              tr: 'cursor-pointer hover:bg-default-100',
            }}
          >
            <TableHeader>
              <TableColumn>Job Class</TableColumn>
              <TableColumn>Queue</TableColumn>
              <TableColumn>Exception</TableColumn>
              <TableColumn>Attempts</TableColumn>
              <TableColumn>Failed At</TableColumn>
              <TableColumn>Actions</TableColumn>
            </TableHeader>
            <TableBody
              items={data?.data ?? []}
              emptyContent="No failed jobs"
            >
              {(job) => (
                <TableRow key={job.id}>
                  <TableCell>
                    <span className="font-mono text-sm">{job.class}</span>
                  </TableCell>
                  <TableCell>
                    <span className="text-sm text-default-500">{job.queue}</span>
                  </TableCell>
                  <TableCell>
                    <div className="max-w-xs truncate">
                      <span className="text-xs font-mono text-danger">
                        {job.exception_class ?? 'Unknown'}
                      </span>
                      {job.exception_message && (
                        <p className="text-xs text-default-400 truncate mt-0.5">
                          {job.exception_message}
                        </p>
                      )}
                    </div>
                  </TableCell>
                  <TableCell>
                    <span className="text-sm">{job.attempts}</span>
                  </TableCell>
                  <TableCell>
                    <span className="text-xs text-default-500">
                      {formatTime(job.failed_at)}
                    </span>
                  </TableCell>
                  <TableCell>
                    <div className="flex gap-1">
                      <Button
                        size="sm"
                        color="primary"
                        variant="flat"
                        onPress={(e) => handleRetry(job.id, e as unknown as React.MouseEvent)}
                        isLoading={retryJob.isPending && retryJob.variables === job.id}
                      >
                        Retry
                      </Button>
                      <Button
                        size="sm"
                        color="danger"
                        variant="flat"
                        onPress={(e) => handleDelete(job.id, e as unknown as React.MouseEvent)}
                      >
                        Delete
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>

          {data && data.data.length > 0 && (
            <div className="flex justify-center pt-2">
              <Pagination
                total={Math.max(1, Math.ceil(100 / perPage))}
                page={page}
                onChange={setPage}
                showControls
                size="sm"
              />
            </div>
          )}
        </>
      )}

      {/* Retry All Confirmation */}
      <Modal isOpen={retryAllModal.isOpen} onOpenChange={retryAllModal.onOpenChange}>
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>Retry All Failed Jobs</ModalHeader>
              <ModalBody>
                <p>
                  Are you sure you want to retry all failed jobs? This will
                  re-enqueue every failed job back into its original queue.
                </p>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  Cancel
                </Button>
                <Button color="warning" onPress={confirmRetryAll}>
                  Retry All
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Delete Confirmation */}
      <Modal isOpen={deleteModal.isOpen} onOpenChange={deleteModal.onOpenChange}>
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>Delete Failed Job</ModalHeader>
              <ModalBody>
                <p>
                  Are you sure you want to permanently delete this failed job?
                  This action cannot be undone.
                </p>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  Cancel
                </Button>
                <Button color="danger" onPress={confirmDelete}>
                  Delete
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
