import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import * as React from 'react';

interface PaginationProps {
    currentPage: number;
    lastPage: number;
    onPageChange: (page: number) => void;
}

export function Pagination({
    currentPage,
    lastPage,
    onPageChange,
}: PaginationProps) {
    const handlePrevious = () => {
        if (currentPage > 1) {
            onPageChange(currentPage - 1);
        }
    };

    const handleNext = () => {
        if (currentPage < lastPage) {
            onPageChange(currentPage + 1);
        }
    };

    const getPageNumbers = () => {
        const delta = 2;
        const range = [];
        const rangeWithDots = [];

        for (
            let i = Math.max(2, currentPage - delta);
            i <= Math.min(lastPage - 1, currentPage + delta);
            i++
        ) {
            range.push(i);
        }

        if (lastPage <= 1) {
            return [];
        }

        const l: (number | string)[] = [];

        for (let i = 1; i <= lastPage; i++) {
            if (i === 1 || i === lastPage || range.includes(i)) {
                l.push(i);
            } else if (l[l.length - 1] !== '...') {
                l.push('...');
            }
        }

        return l;
    };

    if (lastPage <= 1) {
        return null;
    }

    const pageNumbers = getPageNumbers();

    return (
        <div className="flex items-center justify-between gap-2 border-t pt-4">
            <div className="text-sm text-muted-foreground">
                Page {currentPage} of {lastPage}
            </div>
            <div className="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={handlePrevious}
                    disabled={currentPage === 1}
                >
                    <ChevronLeft className="size-4" />
                    Previous
                </Button>

                <div className="flex items-center gap-1">
                    {pageNumbers.map((page, idx) => (
                        <React.Fragment key={idx}>
                            {page === '...' ? (
                                <span className="px-2 text-sm text-muted-foreground">
                                    ...
                                </span>
                            ) : (
                                <Button
                                    variant={
                                        page === currentPage
                                            ? 'default'
                                            : 'outline'
                                    }
                                    size="sm"
                                    onClick={() =>
                                        onPageChange(page as number)
                                    }
                                >
                                    {page}
                                </Button>
                            )}
                        </React.Fragment>
                    ))}
                </div>

                <Button
                    variant="outline"
                    size="sm"
                    onClick={handleNext}
                    disabled={currentPage === lastPage}
                >
                    Next
                    <ChevronRight className="size-4" />
                </Button>
            </div>
        </div>
    );
}
