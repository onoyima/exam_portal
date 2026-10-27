import { useState, useEffect } from 'react';
import { useNavigate, Navigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import {
  Card, Table, Tag, Button, Space, Typography, Spin, Alert, Modal, message,
  Empty, Breadcrumb, Row, Col, Select, Collapse, Progress, Skeleton, Result,
} from 'antd';
import {
  SettingOutlined, BookOutlined, TeamOutlined, CalendarOutlined,
  LinkOutlined, ArrowLeftOutlined, HomeOutlined, ReloadOutlined,
  CheckCircleOutlined, CloseCircleOutlined, RightOutlined,
} from '@ant-design/icons';
import { api } from '../api/client';

const { Title, Text } = Typography;

interface VuSession {
  id: number;
  session: string;
  start_date: string;
  end_date: string;
}

interface HierarchyClass {
  id: number;
  name: string;
  code: string;
  course_code: string;
  course_title: string;
  status: string;
  total_students: number;
  total_staff: number;
  sync_status: string;
}

interface HierarchyLevel {
  level_id: number;
  level_name: string;
  classes: HierarchyClass[];
}

interface HierarchySemester {
  vu_semester_id: number;
  semester_name: string;
  levels: HierarchyLevel[];
}

interface HierarchySession {
  vu_session_id: number;
  vu_session_name: string;
  semesters: HierarchySemester[];
}

export default function Settings() {
  const navigate = useNavigate();
  const { user } = useAuth();
  const role = user?.role || user?.portal_user_type || '';
  const isStudent = role === 'student';
  if (isStudent) return <Navigate to="/dashboard" replace />;

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [hierarchy, setHierarchy] = useState<HierarchySession[]>([]);

  const [vuSessions, setVuSessions] = useState<VuSession[]>([]);
  const [selectedVuSession, setSelectedVuSession] = useState<number | null>(null);
  const [linkOpen, setLinkOpen] = useState(false);
  const [linking, setLinking] = useState(false);
  const [vuSessionsLoading, setVuSessionsLoading] = useState(false);

  const [syncing, setSyncing] = useState<number | null>(null);

  const fetchHierarchy = async (sessionId?: number | null) => {
    setLoading(true);
    setError(null);
    try {
      const params = sessionId ? `?vu_session_id=${sessionId}` : '';
      const res: any = await api.get(`/classes/hierarchy${params}`);
      setHierarchy(res.hierarchy || []);
    } catch (err: any) {
      setError(err?.message || 'Failed to load hierarchy');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchHierarchy(); }, []);

  const openLinkModal = async () => {
    setVuSessionsLoading(true);
    setLinkOpen(true);
    try {
      const res: any = await api.get('/academic/sessions');
      setVuSessions(res.vu_sessions || []);
    } catch {
      message.error('Failed to load academic sessions');
    } finally {
      setVuSessionsLoading(false);
    }
  };

  const handleLinkSession = async () => {
    if (!selectedVuSession) { message.warning('Select a session'); return; }
    setLinking(true);
    try {
      const res: any = await api.post('/classes/sync', { vu_session_id: selectedVuSession });
      message.success({ content: res.message || `Classes synced for session`, duration: 4 });
      setLinkOpen(false);
      setSelectedVuSession(null);
      fetchHierarchy(selectedVuSession);
    } catch (err: any) {
      message.error({ content: err?.message || 'Failed to sync classes', duration: 4 });
    } finally {
      setLinking(false);
    }
  };

  const handleSyncSession = async (sessionId: number) => {
    setSyncing(sessionId);
    try {
      const res: any = await api.post('/classes/sync', { vu_session_id: sessionId });
      message.success({ content: res.message || `Classes synced`, duration: 4 });
      fetchHierarchy(sessionId);
    } catch (err: any) {
      message.error({ content: err?.message || 'Sync failed', duration: 4 });
    } finally {
      setSyncing(null);
    }
  };

  if (loading) {
    return (
      <div>
        <Card style={{ borderRadius: 12, padding: 24 }}>
          <Skeleton active paragraph={{ rows: 10 }} />
        </Card>
      </div>
    );
  }

  if (error) {
    return (
      <div>
        <Result
          status="error"
          title="Failed to load settings"
          subTitle={error}
          extra={<Button type="primary" onClick={() => fetchHierarchy()}>Retry</Button>}
        />
      </div>
    );
  }

  return (
    <div>
      <Breadcrumb
        items={[
          { title: <a onClick={() => navigate('/dashboard')} style={{ cursor: 'pointer' }}><HomeOutlined /> Dashboard</a> },
          { title: <span><SettingOutlined /> Settings</span> },
        ]}
        style={{ marginBottom: 16 }}
      />

      <div style={{
        background: 'linear-gradient(135deg, #0891b2 0%, #06b6d4 100%)',
        borderRadius: 16,
        padding: '24px 28px',
        marginBottom: 24,
      }}>
        <Row align="middle" justify="space-between" wrap gutter={[12, 12]}>
          <Col>
            <Title level={4} style={{ margin: 0, color: '#fff' }}>
              <SettingOutlined /> Academic Settings
            </Title>
            <Text style={{ color: 'rgba(255,255,255,0.8)', fontSize: 13, marginTop: 4, display: 'block' }}>
              Manage academic sessions and class groupings
            </Text>
          </Col>
          <Col>
            <Space wrap>
              <Button type="primary" icon={<LinkOutlined />} onClick={openLinkModal}
                style={{ borderRadius: 8, background: 'rgba(255,255,255,0.2)', border: '1px solid rgba(255,255,255,0.3)', color: '#fff' }}>
                Link Academic Session
              </Button>
              <Button icon={<ReloadOutlined />} onClick={() => fetchHierarchy(selectedVuSession)}
                style={{ borderRadius: 8, background: 'rgba(255,255,255,0.15)', border: '1px solid rgba(255,255,255,0.3)', color: '#fff' }}>
                Refresh
              </Button>
            </Space>
          </Col>
        </Row>
      </div>

      {hierarchy.length === 0 ? (
        <Card style={{ borderRadius: 12 }}>
          <Empty description={
            <span>
              No classes found. Link an academic session to auto-create classes.
              <br />
              <Button type="link" icon={<LinkOutlined />} onClick={openLinkModal} style={{ marginTop: 8 }}>
                Link an Academic Session
              </Button>
            </span>
          } />
        </Card>
      ) : (
        hierarchy.map((session) => {
          const totalClasses = session.semesters.reduce(
            (sum, sem) => sum + sem.levels.reduce(
              (s, lvl) => s + lvl.classes.length, 0
            ), 0
          );
          return (
            <Card
              key={session.vu_session_id}
              title={
                <Space>
                  <CalendarOutlined />
                  <Text strong>{session.vu_session_name}</Text>
                  <Tag style={{ borderRadius: 6 }}>{totalClasses} class{totalClasses !== 1 ? 'es' : ''}</Tag>
                </Space>
              }
              extra={
                <Button
                  size="small"
                  icon={<ReloadOutlined />}
                  onClick={() => handleSyncSession(session.vu_session_id)}
                  loading={syncing === session.vu_session_id}
                  style={{ borderRadius: 6 }}
                >
                  Sync Classes
                </Button>
              }
              style={{ borderRadius: 12, marginBottom: 16 }}
            >
              {session.semesters.map((sem) => (
                <Card
                  key={sem.vu_semester_id}
                  type="inner"
                  title={<Text strong><BookOutlined /> {sem.semester_name}</Text>}
                  style={{ borderRadius: 10, marginBottom: 12 }}
                >
                  {sem.levels.map((lvl) => (
                    <div key={lvl.level_id} style={{ marginBottom: 12 }}>
                      <Text type="secondary" style={{ fontSize: 12, fontWeight: 600, textTransform: 'uppercase', letterSpacing: 0.5 }}>
                        {lvl.level_name}
                      </Text>
                      <Table
                        dataSource={lvl.classes}
                        columns={[
                          { title: '#', key: 'idx', width: 40,
                            render: (_: any, __: any, i: number) => i + 1 },
                          { title: 'Code', dataIndex: 'course_code', key: 'code', width: 100 },
                          { title: 'Course Title', dataIndex: 'course_title', key: 'title', ellipsis: true },
                          { title: 'Status', dataIndex: 'status', key: 'status', width: 90,
                            render: (v: string) => (
                              <Tag color={v === 'active' ? 'green' : 'orange'} style={{ borderRadius: 6 }}>{v}</Tag>
                            ),
                          },
                          { title: 'Students', dataIndex: 'total_students', key: 'students', width: 80, align: 'center' as const,
                            render: (v: number) => <TeamOutlined />,
                          },
                          {
                            title: '', key: 'action', width: 100,
                            render: (_: any, r: HierarchyClass) => (
                              <Button type="link" size="small" icon={<RightOutlined />}
                                onClick={() => navigate(`/classes/${r.id}`)}>
                                Open
                              </Button>
                            ),
                          },
                        ]}
                        rowKey="id"
                        size="small"
                        pagination={false}
                        bordered
                        style={{ borderRadius: 8 }}
                        locale={{ emptyText: <Empty description="No classes" /> }}
                      />
                    </div>
                  ))}
                  {sem.levels.length === 0 && (
                    <Text type="secondary">No levels configured</Text>
                  )}
                </Card>
              ))}
            </Card>
          );
        })
      )}

      <Modal
        title={<span><LinkOutlined /> Link Academic Session</span>}
        open={linkOpen}
        onOk={handleLinkSession}
        onCancel={() => { setLinkOpen(false); setSelectedVuSession(null); }}
        confirmLoading={linking}
        okText="Link & Create Classes"
        okButtonProps={{ disabled: !selectedVuSession }}
        destroyOnClose
      >
        <Space direction="vertical" style={{ width: '100%' }}>
          <Text type="secondary">
            Select an academic session (vu_session) to sync classes. All course assignments for this session will create classes automatically.
          </Text>
          {vuSessionsLoading ? (
            <div style={{ textAlign: 'center', padding: 24 }}><Spin /></div>
          ) : vuSessions.length === 0 ? (
            <Empty description="No academic sessions available" />
          ) : (
            <Select
              style={{ width: '100%' }}
              placeholder="Select an academic session..."
              value={selectedVuSession}
              onChange={setSelectedVuSession}
              options={vuSessions.map((vs) => ({
                value: vs.id,
                label: `${vs.session} (${vs.start_date} — ${vs.end_date})`,
              }))}
            />
          )}
        </Space>
      </Modal>
    </div>
  );
}
