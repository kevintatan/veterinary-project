"use client";

import { api } from "@/lib/api";
import type { Appointment, Professional, ScheduleBlock, ScheduleRange, Service } from "@/lib/types";
import { keepPreviousData, useQuery } from "@tanstack/react-query";

/** Every agenda query key starts with "agenda" so a save can refresh them all at once. */
export const agendaKeys = {
  all: ["agenda"] as const,
  appointments: (branchId: string, from: string, to: string) => ["agenda", "appointments", branchId, from, to] as const,
  professionals: (branchId: string) => ["agenda", "professionals", branchId] as const,
  schedules: (branchId: string) => ["agenda", "schedules", branchId] as const,
  blocks: (from: string, to: string) => ["agenda", "blocks", from, to] as const,
  services: ["agenda", "services"] as const,
};

export function useAppointments(branchId: string, from: string, to: string) {
  return useQuery({
    queryKey: agendaKeys.appointments(branchId, from, to),
    queryFn: () => api<{ data: Appointment[] }>(`appointments?from=${from}&to=${to}&branch_id=${branchId}`).then((r) => r.data),
    enabled: !!branchId,
    placeholderData: keepPreviousData, // navigating days keeps the grid, no flash
    refetchInterval: 60_000,
  });
}

export function useProfessionals(branchId: string) {
  return useQuery({
    queryKey: agendaKeys.professionals(branchId),
    queryFn: () => api<{ data: Professional[] }>(`appointments/professionals?branch_id=${branchId}`).then((r) => r.data),
    enabled: !!branchId,
    staleTime: 5 * 60_000,
  });
}

export function useSchedules(branchId: string) {
  return useQuery({
    queryKey: agendaKeys.schedules(branchId),
    queryFn: () => api<{ data: ScheduleRange[] }>(`schedules?branch_id=${branchId}`).then((r) => r.data),
    enabled: !!branchId,
    staleTime: 5 * 60_000,
  });
}

export function useBlocks(from: string, to: string) {
  return useQuery({
    queryKey: agendaKeys.blocks(from, to),
    queryFn: () => api<{ data: ScheduleBlock[] }>(`schedule-blocks?from=${from}&to=${to}`).then((r) => r.data),
    placeholderData: keepPreviousData,
  });
}

export function useServices() {
  return useQuery({
    queryKey: agendaKeys.services,
    queryFn: () => api<{ data: Service[] }>("services").then((r) => r.data),
    staleTime: 5 * 60_000,
  });
}
