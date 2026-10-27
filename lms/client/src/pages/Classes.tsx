import { useState, useEffect, useCallback, useRef } from 'react';
import { Card, Tag, Button, Input, Select, Space, Typography, Spin, Alert, Row, Col, Tooltip, message, Empty, Progress } from 'antd';
import { SyncOutlined, SearchOutlined, ReloadOutlined, CalendarOutlined, TeamOutlined, UserOutlined, BookOutlined, DatabaseOutlined } from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';
import { api } from '../api/client';
import { useAuth } from '../contexts/AuthContext';

const { Title, Text } = Typography;

interface ClassItem {
  id: number;
  name: string;
  code: string;
  course_code: string;
  course_title: string;
  vu_session_name: string;
  semester_name: string;
  department_name: string;
  total_students: number;
  total_staff: number;
  status: string;
  sync_status: string;
  updated_at: string;
}

interface ClassesResponse {
  data: ClassItem[];
  total: number;
  page: number;
  per_page: number;
  last_page: number;
}

const statusColor: Record<string, string> = {
  active: '#16a34a',
  completed: '#2563eb',
  archived: '#ea580c',
};

const statusBg: Record<string, string> = {
  active: '#f0fdf4',
  completed: '#eff6ff',
  archived: '#fff7ed',
};

const statusLabel: Record<string, string> = {
  active: 'Active',
  completed: 'Completed',
  archived: 'Archived',
};

export default function Classes() {
  const navigate = useNavigate();
  const { user } = useAuth();
  const role = user?.role || user?.portal_user_type || '';
  const isStudent = role === 'student';
  const isStaff = role === 'examiner' || role === 'instructor' || role === 'ta';
  const isAdmin = role === 'super_admin' || role === 'admin';

  const [classes, setClasses] = useState<ClassItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [syncing, setSyncing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('active');
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [lastPage, setLastPage] = useState(1);
  const [activeSession, setActiveSession] = useState<any>(null);
  const [sessionLoading, setSessionLoading] = useState(true);
  const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    api.get('/academic/sessions/active').then((res: any) => {
      setActiveSession(res.session || null);
    }).catch(() => {
      setActiveSession(null);
    }).finally(() => setSessionLoading(false));
  }, []);

  const fetchClasses = useCallback(async (searchTerm: string, status: string, pageNum: number) => {
    setLoading(true);
    setError(null);
    try {
      if (isStudent || isStaff) {
        const res: any = await api.get('/classes/my');
        const data = Array.isArray(res) ? res : (res.classes || []);
        setClasses(data);
        setTotal(data.length);
        setLastPage(1);
      } else {
        const params = new URLSearchParams({ page: String(pageNum), per_page: '50' });
        if (searchTerm) params.set('search', searchTerm);
        if (status) params.set('status', status);
        if (activeSession?.id) params.set('vu_session_id', String(activeSession.id));
        const res: any = await api.get(`/classes?${params}`);
        const data = res as ClassesResponse;
        setClasses(data.data || []);
        setTotal(data.total || 0);
        setLastPage(data.last_page || 1);
      }
    } catch (err: any) {
      setError(err?.message || 'Failed to load classes');
      setClasses([]);
    } finally {
      setLoading(false);
    }
  }, [activeSession, isStudent, isStaff]);

  useEffect(() => {
    if (!sessionLoading) {
      fetchClasses(search, statusFilter, page);
    }
  }, [statusFilter, page, fetchClasses, sessionLoading]);

  const handleSearchChange = (val: string) => {
    setSearch(val);
    if (!isAdmin) return;
    if (searchTimer.current) clearTimeout(searchTimer.current);
    searchTimer.current = setTimeout(() => {
      setPage(1);
      fetchClasses(val, statusFilter, 1);
    }, 400);
  };

  const handleSync = async () => {
    setSyncing(true);
    try {
      const body: any = {};
      if (activeSession?.id) body.vu_session_id = activeSession.id;
      const res: any = await api.post('/classes/sync', body);
      const r = res.sync_result || {};
      const details = [
        r.classes_created ? `${r.classes_created} created` : '',
        r.classes_updated ? `${r.classes_updated} updated` : '',
        r.enrollments_added ? `${r.enrollments_added} enrollments` : '',
        r.staff_assigned ? `${r.staff_assigned} staff` : '',
        r.errors ? `${r.errors} errors` : '',
      ].filter(Boolean).join(', ');
      message.success({ content: `Sync completed: ${details || 'no changes'}`, duration: 5 });
      fetchClasses(search, statusFilter, page);
    } catch (err: any) {
      message.error({ content: err?.message || 'Sync failed. Check portal connection.', duration: 5 });
    } finally {
      setSyncing(false);
    }
  };

  const stats = {
    active: classes.filter((c) => c.status === 'active').length,
    archived: classes.filter((c) => c.status === 'archived').length,
    completed: classes.filter((c) => c.status === 'completed').length,
    totalStudents: classes.reduce((s, c) => s + (c.total_students || 0), 0),
    totalStaff: classes.reduce((s, c) => s + (c.total_staff || 0), 0),
  };

  const studentRatio = stats.totalStudents > 0
    ? Math.min(100, Math.round((stats.active / classes.filter(c => c.status === 'active').length || 1) * 100))
    : 0;

  return (
    <div>
      <div style={{
        background: 'linear-gradient(135deg, #0891b2 0%, #06b6d4 100%)',
        margin: '-24px -24px 24px',
        padding: '28px 24px',
        borderRadius: '0 0 24px 24px',
      }}>
        <Row align="middle" justify="space-between" wrap>
          <Col>
            <Title level={4} style={{ margin: 0, color: '#fff' }}>Classes</Title>
            <Text style={{ color: 'rgba(255,255,255,0.8)', fontSize: 13 }}>
              {activeSession ? `Active session: ${activeSession.session}` : 'Manage all class offerings'}
            </Text>
          </Col>
          <Col>
            <Space wrap>
              {isAdmin && (
                <>
                  <Input
                    placeholder="Search classes..."
                    prefix={<SearchOutlined style={{ color: 'rgba(255,255,255,0.6)' }} />}
                    value={search}
                    onChange={(e) => handleSearchChange(e.target.value)}
                    style={{ width: 220, background: 'rgba(255,255,255,0.15)', border: '1px solid rgba(255,255,255,0.25)', color: '#fff' }}
                    allowClear
                    onClear={() => { setSearch(''); setPage(1); fetchClasses('', statusFilter, 1); }}
                  />
                  <Select
                    value={statusFilter}
                    onChange={(val) => { setStatusFilter(val); setPage(1); }}
                    style={{ width: 130 }}
                    options={[
                      { value: '', label: 'All Status' },
                      { value: 'active', label: 'Active' },
                      { value: 'archived', label: 'Archived' },
                      { value: 'completed', label: 'Completed' },
                    ]}
                  />
                  <Tooltip title="Sync classes from exam portal">
                    <Button icon={<SyncOutlined />} onClick={handleSync} loading={syncing} type="primary" ghost style={{ borderColor: 'rgba(255,255,255,0.5)', color: '#fff' }}>
                      Sync
                    </Button>
                  </Tooltip>
                </>
              )}
            </Space>
          </Col>
        </Row>
      </div>

      <Row gutter={[12, 12]} style={{ marginBottom: 20 }}>
        <Col xs={12} sm={6} lg={3}>
          <Card size="small" styles={{ body: { padding: '14px 16px' } }}
            style={{ borderRadius: 10, borderLeft: '3px solid #16a34a' }}>
            <Statistic title="Active" value={stats.active} suffix={`/ ${classes.length}`}
              valueStyle={{ fontSize: 20, color: '#16a34a', fontWeight: 600 }} />
          </Card>
        </Col>
        <Col xs={12} sm={6} lg={3}>
          <Card size="small" styles={{ body: { padding: '14px 16px' } }}
            style={{ borderRadius: 10, borderLeft: '3px solid #0891b2' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <TeamOutlined style={{ fontSize: 18, color: '#0891b2' }} />
              <div>
                <Text style={{ fontSize: 11, color: '#64748b', display: 'block' }}>Students</Text>
                <Text strong style={{ fontSize: 18 }}>{stats.totalStudents}</Text>
              </div>
            </div>
          </Card>
        </Col>
        <Col xs={12} sm={6} lg={3}>
          <Card size="small" styles={{ body: { padding: '14px 16px' } }}
            style={{ borderRadius: 10, borderLeft: '3px solid #6366f1' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <UserOutlined style={{ fontSize: 18, color: '#6366f1' }} />
              <div>
                <Text style={{ fontSize: 11, color: '#64748b', display: 'block' }}>Staff</Text>
                <Text strong style={{ fontSize: 18 }}>{stats.totalStaff}</Text>
              </div>
            </div>
          </Card>
        </Col>
        <Col xs={12} sm={6} lg={3}>
          <Card size="small" styles={{ body: { padding: '14px 16px' } }}
            style={{ borderRadius: 10, borderLeft: '3px solid #ea580c' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <DatabaseOutlined style={{ fontSize: 18, color: '#ea580c' }} />
              <div>
                <Text style={{ fontSize: 11, color: '#64748b', display: 'block' }}>Archived</Text>
                <Text strong style={{ fontSize: 18 }}>{stats.archived}</Text>
              </div>
            </div>
          </Card>
        </Col>
      </Row>

      {error && (
        <Alert
          message="Failed to load classes"
          description={error}
          type="error"
          showIcon
          style={{ marginBottom: 16, borderRadius: 8 }}
          action={<Button size="small" danger onClick={() => fetchClasses(search, statusFilter, page)} icon={<ReloadOutlined />}>Retry</Button>}
        />
      )}

      {loading ? (
        <Row gutter={[16, 16]}>
          {[1, 2, 3, 4, 5, 6].map((i) => (
            <Col xs={24} sm={12} lg={8} key={i}>
              <Card style={{ borderRadius: 12, overflow: 'hidden' }}>
                <div style={{ height: 4, background: '#e2e8f0', marginBottom: 16, borderRadius: 2 }} />
                <div style={{ height: 20, width: '60%', background: '#e2e8f0', borderRadius: 4, marginBottom: 8 }} />
                <div style={{ height: 16, width: '40%', background: '#e2e8f0', borderRadius: 4, marginBottom: 12 }} />
                <div style={{ display: 'flex', gap: 8, marginBottom: 12 }}>
                  <div style={{ height: 22, width: 80, background: '#e2e8f0', borderRadius: 11 }} />
                  <div style={{ height: 22, width: 60, background: '#e2e8f0', borderRadius: 11 }} />
                </div>
                <div style={{ display: 'flex', gap: 16 }}>
                  <div style={{ height: 16, width: 60, background: '#e2e8f0', borderRadius: 4 }} />
                  <div style={{ height: 16, width: 60, background: '#e2e8f0', borderRadius: 4 }} />
                </div>
              </Card>
            </Col>
          ))}
        </Row>
      ) : classes.length === 0 ? (
        <Card style={{ borderRadius: 12 }}>
          <Empty
            description={search ? 'No matching classes' : 'No classes yet. Sync from the exam portal to get started.'}
            image={Empty.PRESENTED_IMAGE_SIMPLE}
          />
        </Card>
      ) : (
        <>
          <div style={{ marginBottom: 12, color: '#64748b', fontSize: 13 }}>
            Showing <Text strong>{classes.length}</Text> of <Text strong>{total}</Text> classes
          </div>
          <Row gutter={[16, 16]}>
            {classes.map((c) => {
              const sColor = statusColor[c.status] || '#6b7280';
              const sBg = statusBg[c.status] || '#f9fafb';
              const studentPct = c.total_students > 0 && stats.totalStudents > 0
                ? Math.round((c.total_students / stats.totalStudents) * 100)
                : 0;
              return (
                <Col xs={24} sm={12} lg={8} key={c.id}>
                  <Card
                    hoverable
                    onClick={() => navigate(`/classes/${c.id}`)}
                    style={{
                      borderRadius: 12,
                      overflow: 'hidden',
                      height: '100%',
                      transition: 'all 0.25s ease',
                      border: '1px solid #f1f5f9',
                    }}
                    styles={{
                      body: { padding: 0 },
                    }}
                  >
                    <div style={{ height: 4, background: `linear-gradient(90deg, ${sColor}, ${sColor}88)` }} />
                    <div style={{ padding: '16px 18px 14px' }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 8 }}>
                        <div style={{ flex: 1, minWidth: 0 }}>
                          <Text style={{ fontSize: 13, color: '#0891b2', fontWeight: 600, display: 'block', marginBottom: 2 }}>
                            {c.course_code || c.code || 'N/A'}
                          </Text>
                          <Tooltip title={c.course_title || c.name}>
                            <Text strong style={{ fontSize: 15, color: '#0f172a', display: 'block' }} ellipsis>
                              {c.course_title || c.name}
                            </Text>
                          </Tooltip>
                        </div>
                        <Tag color={c.status === 'active' ? 'green' : c.status === 'completed' ? 'blue' : 'orange'}
                          style={{ borderRadius: 6, marginLeft: 8, flexShrink: 0, fontSize: 11 }}>
                          {statusLabel[c.status] || c.status}
                        </Tag>
                      </div>

                      <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginBottom: 10 }}>
                        {c.vu_session_name && (
                          <Tag icon={<CalendarOutlined />} style={{ borderRadius: 6, fontSize: 11, margin: 0, background: '#f0f9ff', border: 'none', color: '#0369a1' }}>
                            {c.vu_session_name}
                          </Tag>
                        )}
                        {c.semester_name && (
                          <Tag style={{ borderRadius: 6, fontSize: 11, margin: 0, background: '#faf5ff', border: 'none', color: '#7e22ce' }}>
                            {c.semester_name}
                          </Tag>
                        )}
                        {c.department_name && (
                          <Tag style={{ borderRadius: 6, fontSize: 11, margin: 0, background: '#f0fdf4', border: 'none', color: '#15803d' }}>
                            {c.department_name}
                          </Tag>
                        )}
                      </div>

                      <div style={{
                        display: 'flex', gap: 20, padding: '10px 0', borderTop: '1px solid #f1f5f9',
                        borderBottom: '1px solid #f1f5f9', marginBottom: 10,
                      }}>
                        <div>
                          <TeamOutlined style={{ fontSize: 13, color: '#64748b', marginRight: 4 }} />
                          <Text style={{ fontSize: 13, fontWeight: 600, color: '#0f172a' }}>{c.total_students ?? 0}</Text>
                          <Text style={{ fontSize: 11, color: '#94a3b8', marginLeft: 2 }}>students</Text>
                        </div>
                        <div>
                          <UserOutlined style={{ fontSize: 13, color: '#64748b', marginRight: 4 }} />
                          <Text style={{ fontSize: 13, fontWeight: 600, color: '#0f172a' }}>{c.total_staff ?? 0}</Text>
                          <Text style={{ fontSize: 11, color: '#94a3b8', marginLeft: 2 }}>staff</Text>
                        </div>
                      </div>

                      <Progress
                        percent={studentPct}
                        showInfo={false}
                        strokeColor={sColor}
                        trailColor="#e2e8f0"
                        size="small"
                        style={{ marginBottom: 2 }}
                      />
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <Text style={{ fontSize: 11, color: '#94a3b8' }}>
                          Enrollment density
                        </Text>
                        <Text style={{ fontSize: 11, color: sColor, fontWeight: 600 }}>
                          {studentPct}%
                        </Text>
                      </div>
                    </div>
                  </Card>
                </Col>
              );
            })}
          </Row>
        </>
      )}
    </div>
  );
}

function Statistic({ title, value, suffix, valueStyle }: { title: string; value: number | string; suffix?: React.ReactNode; valueStyle?: React.CSSProperties }) {
  return (
    <div>
      <Text style={{ fontSize: 11, color: '#64748b', display: 'block', marginBottom: 2 }}>{title}</Text>
      <Text style={{ fontSize: 22, fontWeight: 700, ...valueStyle }}>
        {value}{suffix && <Text style={{ fontSize: 14, fontWeight: 400, color: '#94a3b8', marginLeft: 2 }}>{suffix}</Text>}
      </Text>
    </div>
  );
}
