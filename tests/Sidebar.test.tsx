import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import Sidebar from '../src/components/Sidebar';
import { menu } from '../src/menu';

describe('Sidebar component', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  test('renders and toggles collapse state', () => {
    render(<Sidebar items={menu} initialCollapsed={false} />);
    // collapse button exists
    const toggle = screen.getByRole('button', { name: /collapse sidebar/i });
    expect(toggle).toBeInTheDocument();

    // click to collapse
    fireEvent.click(toggle);
    expect(localStorage.getItem('sidebar_collapsed')).toBe('1');
  });

  test('mobile drawer opens and closes', () => {
    render(<Sidebar items={menu} />);
    const openBtn = screen.getByLabelText('Open menu');
    fireEvent.click(openBtn);
    // Close button inside mobile drawer
    expect(screen.getByRole('button', { name: /close menu/i })).toBeInTheDocument();
    const closeBtn = screen.getByRole('button', { name: /close menu/i });
    fireEvent.click(closeBtn);
    // drawer should close (close button removed)
    expect(screen.queryByRole('button', { name: /close menu/i })).not.toBeInTheDocument();
  });
});
