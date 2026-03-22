import type {
  AuthResponse,
  Contact,
  Company,
  Deal,
  Activity,
  Note,
  DashboardStats,
  PaginatedResponse,
} from './types';

const API_BASE = process.env.NEXT_PUBLIC_API_URL || '/api';

export class ApiError extends Error {
  constructor(
    public status: number,
    public body: Record<string, unknown>,
  ) {
    super(`API Error ${status}: ${JSON.stringify(body)}`);
  }
}

class ApiClient {
  private token: string | null = null;

  setToken(token: string | null) {
    this.token = token;
    if (typeof window !== 'undefined') {
      if (token) {
        localStorage.setItem('crm_token', token);
      } else {
        localStorage.removeItem('crm_token');
      }
    }
  }

  getToken(): string | null {
    if (this.token) return this.token;
    if (typeof window !== 'undefined') {
      this.token = localStorage.getItem('crm_token');
    }
    return this.token;
  }

  private getHeaders(): HeadersInit {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'X-Workspace-Id': '1',
    };
    const token = this.getToken();
    if (token) headers['Authorization'] = `Bearer ${token}`;
    return headers;
  }

  async get<T>(path: string): Promise<T> {
    const res = await fetch(`${API_BASE}${path}`, {
      headers: this.getHeaders(),
    });
    if (!res.ok) {
      const body = await res.json().catch(() => ({}));
      throw new ApiError(res.status, body);
    }
    return res.json();
  }

  async post<T>(path: string, body: unknown): Promise<T> {
    const res = await fetch(`${API_BASE}${path}`, {
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify(body),
    });
    if (!res.ok) {
      const errBody = await res.json().catch(() => ({}));
      throw new ApiError(res.status, errBody);
    }
    return res.json();
  }

  async put<T>(path: string, body: unknown): Promise<T> {
    const res = await fetch(`${API_BASE}${path}`, {
      method: 'PUT',
      headers: this.getHeaders(),
      body: JSON.stringify(body),
    });
    if (!res.ok) {
      const errBody = await res.json().catch(() => ({}));
      throw new ApiError(res.status, errBody);
    }
    return res.json();
  }

  async patch<T>(path: string, body: unknown): Promise<T> {
    const res = await fetch(`${API_BASE}${path}`, {
      method: 'PATCH',
      headers: this.getHeaders(),
      body: JSON.stringify(body),
    });
    if (!res.ok) {
      const errBody = await res.json().catch(() => ({}));
      throw new ApiError(res.status, errBody);
    }
    return res.json();
  }

  async delete(path: string): Promise<void> {
    const res = await fetch(`${API_BASE}${path}`, {
      method: 'DELETE',
      headers: this.getHeaders(),
    });
    if (!res.ok) {
      const errBody = await res.json().catch(() => ({}));
      throw new ApiError(res.status, errBody);
    }
  }

  // Auth
  async login(email: string, password: string): Promise<AuthResponse> {
    return this.post<AuthResponse>('/auth/login', { email, password });
  }

  // Dashboard
  async getDashboardStats(): Promise<DashboardStats> {
    return this.get<DashboardStats>('/dashboard/stats');
  }

  // Contacts
  async getContacts(params?: { page?: number; search?: string; status?: string }): Promise<PaginatedResponse<Contact>> {
    const query = new URLSearchParams();
    if (params?.page) query.set('page', String(params.page));
    if (params?.search) query.set('search', params.search);
    if (params?.status) query.set('status', params.status);
    const qs = query.toString();
    return this.get<PaginatedResponse<Contact>>(`/contacts${qs ? `?${qs}` : ''}`);
  }

  async getContact(id: number): Promise<Contact> {
    return this.get<Contact>(`/contacts/${id}`);
  }

  async createContact(data: Partial<Contact>): Promise<Contact> {
    return this.post<Contact>('/contacts', data);
  }

  async updateContact(id: number, data: Partial<Contact>): Promise<Contact> {
    return this.put<Contact>(`/contacts/${id}`, data);
  }

  async deleteContact(id: number): Promise<void> {
    return this.delete(`/contacts/${id}`);
  }

  // Companies
  async getCompanies(params?: { page?: number; search?: string }): Promise<PaginatedResponse<Company>> {
    const query = new URLSearchParams();
    if (params?.page) query.set('page', String(params.page));
    if (params?.search) query.set('search', params.search);
    const qs = query.toString();
    return this.get<PaginatedResponse<Company>>(`/companies${qs ? `?${qs}` : ''}`);
  }

  async getCompany(id: number): Promise<Company> {
    return this.get<Company>(`/companies/${id}`);
  }

  async createCompany(data: Partial<Company>): Promise<Company> {
    return this.post<Company>('/companies', data);
  }

  async updateCompany(id: number, data: Partial<Company>): Promise<Company> {
    return this.put<Company>(`/companies/${id}`, data);
  }

  async deleteCompany(id: number): Promise<void> {
    return this.delete(`/companies/${id}`);
  }

  // Deals
  async getDeals(params?: { page?: number; stage?: string }): Promise<PaginatedResponse<Deal>> {
    const query = new URLSearchParams();
    if (params?.page) query.set('page', String(params.page));
    if (params?.stage) query.set('stage', params.stage);
    const qs = query.toString();
    return this.get<PaginatedResponse<Deal>>(`/deals${qs ? `?${qs}` : ''}`);
  }

  async getDeal(id: number): Promise<Deal> {
    return this.get<Deal>(`/deals/${id}`);
  }

  async createDeal(data: Partial<Deal>): Promise<Deal> {
    return this.post<Deal>('/deals', data);
  }

  async updateDeal(id: number, data: Partial<Deal>): Promise<Deal> {
    return this.put<Deal>(`/deals/${id}`, data);
  }

  async deleteDeal(id: number): Promise<void> {
    return this.delete(`/deals/${id}`);
  }

  // Activities
  async getActivities(params?: { page?: number; filter?: string }): Promise<PaginatedResponse<Activity>> {
    const query = new URLSearchParams();
    if (params?.page) query.set('page', String(params.page));
    if (params?.filter) query.set('filter', params.filter);
    const qs = query.toString();
    return this.get<PaginatedResponse<Activity>>(`/activities${qs ? `?${qs}` : ''}`);
  }

  async getActivity(id: number): Promise<Activity> {
    return this.get<Activity>(`/activities/${id}`);
  }

  async createActivity(data: Partial<Activity>): Promise<Activity> {
    return this.post<Activity>('/activities', data);
  }

  async updateActivity(id: number, data: Partial<Activity>): Promise<Activity> {
    return this.put<Activity>(`/activities/${id}`, data);
  }

  async completeActivity(id: number): Promise<Activity> {
    return this.patch<Activity>(`/activities/${id}/complete`, {});
  }

  // Notes
  async getNotes(type: string, id: number): Promise<Note[]> {
    return this.get<Note[]>(`/notes?notable_type=${type}&notable_id=${id}`);
  }

  async createNote(data: { body: string; notable_type: string; notable_id: number }): Promise<Note> {
    return this.post<Note>('/notes', data);
  }

  // Contact sub-resources
  async getContactDeals(contactId: number): Promise<Deal[]> {
    return this.get<Deal[]>(`/contacts/${contactId}/deals`);
  }

  async getContactActivities(contactId: number): Promise<Activity[]> {
    return this.get<Activity[]>(`/contacts/${contactId}/activities`);
  }
}

export const api = new ApiClient();
