import { Button } from '@/components/ui/button';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
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
        <Box
            data-slot="pagination"
            sx={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: 1,
                borderTop: '1px solid',
                borderColor: 'divider',
                pt: 2,
            }}
        >
            <Typography
                variant="body2"
                sx={{ color: 'text.secondary' }}
            >
                Page {currentPage} of {lastPage}
            </Typography>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={handlePrevious}
                    disabled={currentPage === 1}
                >
                    <ChevronLeft className="size-4" />
                    Previous
                </Button>

                <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                    {pageNumbers.map((page, idx) => (
                        <React.Fragment key={idx}>
                            {page === '...' ? (
                                <Typography
                                    component="span"
                                    variant="body2"
                                    sx={{
                                        px: 1,
                                        color: 'text.secondary',
                                    }}
                                >
                                    ...
                                </Typography>
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
                </Box>

                <Button
                    variant="outline"
                    size="sm"
                    onClick={handleNext}
                    disabled={currentPage === lastPage}
                >
                    Next
                    <ChevronRight className="size-4" />
                </Button>
            </Box>
        </Box>
    );
}
