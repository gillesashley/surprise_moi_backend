import * as React from 'react';
import {
    Table as MuiTable,
    TableBody as MuiTableBody,
    TableCell as MuiTableCell,
    TableContainer,
    TableFooter as MuiTableFooter,
    TableHead as MuiTableHead,
    TableRow as MuiTableRow,
    Typography,
} from '@mui/material';

function Table({
    className,
    ref,
    children,
    ...props
}: React.HTMLAttributes<HTMLTableElement> & {
    ref?: React.Ref<HTMLTableElement>;
}) {
    return (
        <TableContainer
            data-slot="table"
            sx={{ position: 'relative', width: '100%', overflowX: 'auto' }}
        >
            <MuiTable
                ref={ref}
                className={className}
                sx={{ width: '100%', fontSize: '0.875rem' }}
                {...(props as object)}
            >
                {children}
            </MuiTable>
        </TableContainer>
    );
}

function TableHeader({
    className,
    ref,
    children,
    ...props
}: React.HTMLAttributes<HTMLTableSectionElement> & {
    ref?: React.Ref<HTMLTableSectionElement>;
}) {
    return (
        <MuiTableHead
            ref={ref}
            data-slot="table-header"
            className={className}
            {...(props as object)}
        >
            {children}
        </MuiTableHead>
    );
}

function TableBody({
    className,
    ref,
    children,
    ...props
}: React.HTMLAttributes<HTMLTableSectionElement> & {
    ref?: React.Ref<HTMLTableSectionElement>;
}) {
    return (
        <MuiTableBody
            ref={ref}
            data-slot="table-body"
            className={className}
            {...(props as object)}
        >
            {children}
        </MuiTableBody>
    );
}

function TableFooter({
    className,
    ref,
    children,
    ...props
}: React.HTMLAttributes<HTMLTableSectionElement> & {
    ref?: React.Ref<HTMLTableSectionElement>;
}) {
    return (
        <MuiTableFooter
            ref={ref}
            data-slot="table-footer"
            className={className}
            {...(props as object)}
        >
            {children}
        </MuiTableFooter>
    );
}

function TableRow({
    className,
    ref,
    children,
    ...props
}: React.HTMLAttributes<HTMLTableRowElement> & {
    ref?: React.Ref<HTMLTableRowElement>;
}) {
    return (
        <MuiTableRow
            ref={ref}
            hover
            data-slot="table-row"
            className={className}
            {...(props as object)}
        >
            {children}
        </MuiTableRow>
    );
}

function TableHead({
    className,
    ref,
    children,
    ...props
}: React.ThHTMLAttributes<HTMLTableCellElement> & {
    ref?: React.Ref<HTMLTableCellElement>;
}) {
    return (
        <MuiTableCell
            ref={ref}
            component="th"
            data-slot="table-head"
            className={className}
            sx={{
                height: 48,
                fontWeight: 500,
                textAlign: 'left',
                verticalAlign: 'middle',
                color: 'text.secondary',
            }}
            {...(props as object)}
        >
            {children}
        </MuiTableCell>
    );
}

function TableCell({
    className,
    ref,
    children,
    ...props
}: React.TdHTMLAttributes<HTMLTableCellElement> & {
    ref?: React.Ref<HTMLTableCellElement>;
}) {
    return (
        <MuiTableCell
            ref={ref}
            data-slot="table-cell"
            className={className}
            sx={{
                verticalAlign: 'middle',
            }}
            {...(props as object)}
        >
            {children}
        </MuiTableCell>
    );
}

function TableCaption({
    className,
    ref,
    children,
    ...props
}: React.HTMLAttributes<HTMLTableCaptionElement> & {
    ref?: React.Ref<HTMLTableCaptionElement>;
}) {
    return (
        <caption ref={ref} data-slot="table-caption" className={className}>
            <Typography
                variant="body2"
                color="text.secondary"
                sx={{ mt: 2 }}
                {...(props as object)}
            >
                {children}
            </Typography>
        </caption>
    );
}

export {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
};
