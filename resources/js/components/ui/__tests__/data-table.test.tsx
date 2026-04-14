import * as React from 'react';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import { type ColumnDef } from '@tanstack/react-table';

import { DataTable } from '../data-table';

interface RowData {
    id: number;
    name: string;
}

describe('DataTable', () => {
    const columns: ColumnDef<RowData>[] = [
        {
            accessorKey: 'name',
            header: 'Name',
        },
    ];

    it('propagates rtl direction and keeps headers logically aligned', () => {
        const { container } = render(
            <DataTable
                columns={columns}
                data={[{ id: 1, name: 'Salary' }]}
                dir="rtl"
                getRowId={(row) => row.id}
            />
        );

        expect(container.firstChild).toHaveAttribute('dir', 'rtl');

        const table = screen.getByRole('table');

        expect(table).toHaveAttribute('dir', 'rtl');
        expect(screen.getByRole('columnheader', { name: 'Name' })).toHaveClass('text-start');
    });
});