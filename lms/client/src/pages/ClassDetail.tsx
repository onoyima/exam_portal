import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Card, Tabs, Table, Tag, Button, Select, Space, Typography, Spin, Modal, message,
  Descriptions, Empty, Popconfirm, Tooltip, Breadcrumb, Row, Col, Statistic as AntStatistic, Form,
  Skeleton, Result, Badge,
} from 'antd';
import {
  TeamOutlined, UserOutlined, ReloadOutlined,
  PlusOutlined, DeleteOutlined, ArrowLeftOutlined, HomeOutlined,
  ExclamationCircleOutlined, TrophyOutlined,
  InfoCircleOutlined,
} from '@ant-design/icons';
import { api } from '../api/client';

const { Title, Text, Paragraph } = Typography;

interface Enrollment {
  id: number;
  student_id: number;
  lms_user_id: number | null;
  matric_no: string;
  student_name: string;
  ca_one: number | null;
  ca_two: number | null;
  ca_three: number | null;
  examination: number | null;
  total: number | null;
  grade: string;
  status: string;
  enrollment_source: string;
}

interface Staff {
  id: number;
  staff_id: number;
  lms_user_id: number | null;
  staff_name: string;
  role: string;
}

interface ClassDataRaw {
  id: number;
  name: string;
  code: string;
  course_code: string;
  course_title: string;
  course_id: number;
  lms_course_id: number | null;
  credit_load: number;
  vu_session_name: string;
  semester_name: string;
  department_name: string;
  faculty_name: string;
  course_study_name: string;
  total_students: number;
  total_staff: number;
  status: string;
  sync_status: string;
  created_at: string;
  updated_at: string;
  enrollments: Enrollment[];
  staff: Staff[];
}

function gradeColor(grade: string): string {
  if (!grade) return 'default';
  if (['A', 'B'].includes(grade)) return 'green';
  if (['C', 'D'].includes(grade)) return 'blue';
  return 'red';
}

export default function ClassDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [classData, setClassData] = useState<ClassDataRaw | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [staffOpen, setStaffOpen] = useState(false);
  const [staffLoading, setStaffLoading] = useState(false);
  const [staffId, setStaffId] = useState<number | null>(null);
  const [staffRole, setStaffRole] = useState('assistant');
  const [staffSearchResults, setStaffSearchResults] = useState<any[]>([]);
  const [staffSearching, setStaffSearching] = useState(false);
  const staffSearchRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const fetchClass = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res: any = await api.get(`/classes/${id}`);
      if (!res.class) throw new Error('Class not found');
      setClassData(res.class as ClassDataRaw);
    } catch (err: any) {
      setError(err?.message || 'Failed to load class');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => { fetchClass(); }, [fetchClass]);

  const handleRemoveStudent = async (studentId: number, studentName: string) => {
    try {
      await api.delete(`/classes/${id}/enroll/${studentId}`);
      message.success({ content: `Removed ${studentName || `student #${studentId}`}`, duration: 3 });
      fetchClass();
    } catch (err: any) {
      message.error({ content: err?.message || 'Failed to remove student', duration: 4 });
    }
  };

  const handleAssignStaff = async () => {
    if (!staffId) { message.warning('Select a staff member'); return; }
    setStaffLoading(true);
    try {
      const selected = staffSearchResults.find((s) => s.id === staffId);
      const name = selected ? `${selected.lname} ${selected.fname}`.trim() : `#${staffId}`;
      await api.post(`/classes/${id}/staff`, { staff_id: staffId, role: staffRole });
      message.success({ content: `${name} assigned as ${staffRole}`, duration: 3 });
      setStaffOpen(false);
      setStaffId(null);
      setStaffRole('assistant');
      setStaffSearchResults([]);
      fetchClass();
    } catch (err: any) {
      message.error({ content: err?.message || 'Failed to assign staff', duration: 4 });
    } finally {
      setStaffLoading(false);
    }
  };

  const handleStaffSearch = (value: string) => {
    if (staffSearchRef.current) clearTimeout(staffSearchRef.current);
    if (!value || value.length < 2) {
      setStaffSearchResults([]);
      return;
    }
    staffSearchRef.current = setTimeout(async () => {
      setStaffSearching(true);
      try {
        const res: any = await api.get(`/admin/staff?search=${encodeURIComponent(value)}`);
        setStaffSearchResults(res.staff || []);
      } catch {
        setStaffSearchResults([]);
      } finally {
        setStaffSearching(false);
      }
    }, 300);
  };

  const handleRemoveStaff = async (staffId: number, staffName: string) => {
    try {
      await api.delete(`/classes/${id}/staff/${staffId}`);
      message.success({ content: `Removed ${staffName || `staff #${staffId}`}`, duration: 3 });
      fetchClass();
    } catch (err: any) {
      message.error({ content: err?.message || 'Failed to remove staff', duration: 4 });
    }
  };

  if (loading) {
    return (
      <div>
        <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/classes')} style={{ marginBottom: 16 }}>Back</Button>
        <Card style={{ borderRadius: 12 }}><Skeleton active paragraph={{ rows: 8 }} /></Card>
      </div>
    );
  }

  if (error) {
    return (
      <div>
        <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/classes')} style={{ marginBottom: 16 }}>Back to Classes</Button>
        <Result
          status="error"
          title="Failed to load class"
          subTitle={error}
          extra={[
            <Button key="retry" type="primary" onClick={fetchClass} icon={<ReloadOutlined />}>Retry</Button>,
            <Button key="back" onClick={() => navigate('/classes')}>Back to Classes</Button>,
          ]}
        />
      </div>
    );
  }

  if (!classData) {
    return (
      <div>
        <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/classes')} style={{ marginBottom: 16 }}>Back to Classes</Button>
        <Result
          status="404"
          title="Class Not Found"
          subTitle="The class you're looking for doesn't exist or has been removed."
          extra={<Button type="primary" onClick={() => navigate('/classes')}>Back to Classes</Button>}
        />
      </div>
    );
  }

  const enrollments = classData.enrollments || [];
  const staffList = classData.staff || [];
  const activeEnrollments = enrollments.filter((e) => e.status === 'active');
  const gradedEnrollments = enrollments.filter((e) => e.grade);
  const passCount = gradedEnrollments.filter((e) => e.grade && !['F', 'E'].includes(e.grade)).length;
  const passRate = gradedEnrollments.length > 0 ? Math.round((passCount / gradedEnrollments.length) * 100) : 0;

  const studentColumns = [
    { title: '#', key: 'index', width: 40, fixed: 'left' as const,
      render: (_: any, __: any, i: number) => i + 1 },
    { title: 'Matric No', dataIndex: 'matric_no', key: 'matric', width: 120,
      render: (v: string) => v || '-' },
    { title: 'Student Name', dataIndex: 'student_name', key: 'name', ellipsis: true },
    {
      title: 'CA 1', dataIndex: 'ca_one', key: 'ca1', width: 65, align: 'center' as const,
      render: (v: any) => v !== null && v !== undefined ? v : <Text type="secondary">-</Text>,
    },
    {
      title: 'CA 2', dataIndex: 'ca_two', key: 'ca2', width: 65, align: 'center' as const,
      render: (v: any) => v !== null && v !== undefined ? v : <Text type="secondary">-</Text>,
    },
    {
      title: 'CA 3', dataIndex: 'ca_three', key: 'ca3', width: 65, align: 'center' as const,
      render: (v: any) => v !== null && v !== undefined ? v : <Text type="secondary">-</Text>,
    },
    {
      title: 'Exam', dataIndex: 'examination', key: 'exam', width: 65, align: 'center' as const,
      render: (v: any) => v !== null && v !== undefined ? v : <Text type="secondary">-</Text>,
    },
    {
      title: 'Total', dataIndex: 'total', key: 'total', width: 65, align: 'center' as const,
      sorter: (a: any, b: any) => (a.total || 0) - (b.total || 0),
      render: (v: any) => v !== null && v !== undefined ? <Text strong>{v}</Text> : <Text type="secondary">-</Text>,
    },
    {
      title: 'Grade', dataIndex: 'grade', key: 'grade', width: 70, align: 'center' as const,
      sorter: (a: any, b: any) => (a.grade || '').localeCompare(b.grade || ''),
      render: (v: string) => {
        if (!v) return <Text type="secondary">-</Text>;
        const color = gradeColor(v);
        return <Tag color={color} style={{ minWidth: 28, textAlign: 'center', borderRadius: 6 }}>{v}</Tag>;
      },
    },
    {
      title: 'Status', dataIndex: 'status', key: 'status', width: 85,
      render: (v: string) => {
        const color = v === 'active' ? 'green' : v === 'dropped' ? 'red' : 'default';
        return <Tag color={color} style={{ borderRadius: 6 }}>{v}</Tag>;
      },
    },
    {
      title: '', key: 'action', width: 70, fixed: 'right' as const,
      render: (_: any, r: Enrollment) => (
        <Popconfirm
          title="Remove this student?"
          description={`Remove ${r.student_name || `#${r.student_id}`} from the class?`}
          onConfirm={() => handleRemoveStudent(r.student_id, r.student_name)}
          okText="Remove"
          cancelText="Cancel"
          okButtonProps={{ danger: true }}
          icon={<ExclamationCircleOutlined style={{ color: 'red' }} />}
        >
          <Tooltip title="Remove student">
            <Button type="link" danger icon={<DeleteOutlined />} size="small">Remove</Button>
          </Tooltip>
        </Popconfirm>
      ),
    },
  ];

  const staffColumns = [
    { title: '#', key: 'index', width: 40, render: (_: any, __: any, i: number) => i + 1 },
    { title: 'Staff Name', dataIndex: 'staff_name', key: 'name', ellipsis: true },
    {
      title: 'Role', dataIndex: 'role', key: 'role', width: 130,
      render: (v: string) => {
        const color = v === 'primary' ? 'blue' : v === 'assistant' ? 'green' : v === 'guest' ? 'orange' : 'default';
        return <Tag color={color} style={{ borderRadius: 6 }}>{v}</Tag>;
      },
    },
    {
      title: '', key: 'action', width: 70,
      render: (_: any, r: Staff) => (
        <Popconfirm
          title="Remove this staff member?"
          description={`Remove ${r.staff_name || `#${r.staff_id}`} from the class?`}
          onConfirm={() => handleRemoveStaff(r.staff_id, r.staff_name)}
          okText="Remove"
          cancelText="Cancel"
          okButtonProps={{ danger: true }}
          icon={<ExclamationCircleOutlined style={{ color: 'red' }} />}
        >
          <Tooltip title="Remove staff">
            <Button type="link" danger icon={<DeleteOutlined />} size="small">Remove</Button>
          </Tooltip>
        </Popconfirm>
      ),
    },
  ];

  const tabItems = [
    {
      key: 'students',
      label: (
        <span>
          <TeamOutlined /> Students
          <Badge count={enrollments.length} size="small" style={{ marginLeft: 6, backgroundColor: '#0891b2' }} />
        </span>
      ),
      children: (
        <div>
          <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
            <Col xs={12} sm={6}>
              <Card size="small" styles={{ body: { padding: '14px 16px' } }}
                style={{ borderRadius: 10, borderLeft: '3px solid #0891b2' }}>
                <AntStatistic title="Total Enrolled" value={enrollments.length}
                  prefix={<TeamOutlined />} valueStyle={{ fontSize: 20, fontWeight: 600 }} />
              </Card>
            </Col>
            <Col xs={12} sm={6}>
              <Card size="small" styles={{ body: { padding: '14px 16px' } }}
                style={{ borderRadius: 10, borderLeft: '3px solid #16a34a' }}>
                <AntStatistic title="Active" value={activeEnrollments.length}
                  valueStyle={{ fontSize: 20, color: '#16a34a', fontWeight: 600 }} />
              </Card>
            </Col>
            <Col xs={12} sm={6}>
              <Card size="small" styles={{ body: { padding: '14px 16px' } }}
                style={{ borderRadius: 10, borderLeft: '3px solid #6366f1' }}>
                <AntStatistic title="Graded" value={gradedEnrollments.length}
                  valueStyle={{ fontSize: 20, fontWeight: 600 }} />
              </Card>
            </Col>
            <Col xs={12} sm={6}>
              <Card size="small" styles={{ body: { padding: '14px 16px' } }}
                style={{ borderRadius: 10, borderLeft: '3px solid #f59e0b' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <TrophyOutlined style={{ fontSize: 18, color: '#f59e0b' }} />
                  <div>
                    <Text style={{ fontSize: 11, color: '#64748b', display: 'block' }}>Pass Rate</Text>
                    <Text strong style={{ fontSize: 20, color: '#f59e0b' }}>{passRate}%</Text>
                  </div>
                </div>
              </Card>
            </Col>
          </Row>
          <Text type="secondary" style={{ display: 'block', marginBottom: 16 }}>
            Students are automatically enrolled based on course registration records.
          </Text>
          <div style={{ overflowX: 'auto' }}>
            <Table
              dataSource={enrollments}
              columns={studentColumns}
              rowKey="id"
              size="small"
              bordered
              pagination={{ pageSize: 25, showSizeChanger: true, showTotal: (t) => `${t} students` }}
              scroll={{ x: 900 }}
              locale={{ emptyText: <Empty description="No students enrolled" /> }}
              style={{ borderRadius: 8 }}
            />
          </div>
        </div>
      ),
    },
    {
      key: 'staff',
      label: (
        <span>
          <UserOutlined /> Staff
          <Badge count={staffList.length} size="small" style={{ marginLeft: 6, backgroundColor: '#6366f1' }} />
        </span>
      ),
      children: (
        <div>
          <Space style={{ marginBottom: 16 }}>
            <Button type="primary" icon={<PlusOutlined />} onClick={() => setStaffOpen(true)}
              style={{ borderRadius: 8 }}>Assign Staff</Button>
          </Space>
          {staffList.length === 0 ? (
            <Empty description="No staff assigned" />
          ) : (
            <Table
              dataSource={staffList}
              columns={staffColumns}
              rowKey="id"
              size="small"
              bordered
              pagination={false}
              style={{ borderRadius: 8 }}
            />
          )}
        </div>
      ),
    },
    {
      key: 'info',
      label: <span><InfoCircleOutlined /> Info</span>,
      children: (
        <Row gutter={[24, 24]}>
          <Col xs={24} md={12}>
            <Card title="Course Details" size="small" style={{ borderRadius: 10 }}>
              <Descriptions bordered column={1} size="small">
                <Descriptions.Item label="Code">{classData.course_code || '-'}</Descriptions.Item>
                <Descriptions.Item label="Title">{classData.course_title || '-'}</Descriptions.Item>
                <Descriptions.Item label="Credit Load">{classData.credit_load || '-'}</Descriptions.Item>
                <Descriptions.Item label="Program">{classData.course_study_name || '-'}</Descriptions.Item>
              </Descriptions>
            </Card>
          </Col>
          <Col xs={24} md={12}>
            <Card title="Academic Context" size="small" style={{ borderRadius: 10 }}>
              <Descriptions bordered column={1} size="small">
                <Descriptions.Item label="Session">{classData.vu_session_name || '-'}</Descriptions.Item>
                <Descriptions.Item label="Semester">{classData.semester_name || '-'}</Descriptions.Item>
                <Descriptions.Item label="Department">{classData.department_name || '-'}</Descriptions.Item>
                <Descriptions.Item label="Faculty">{classData.faculty_name || '-'}</Descriptions.Item>
              </Descriptions>
            </Card>
          </Col>
          <Col xs={24}>
            <Card title="Class Status" size="small" style={{ borderRadius: 10 }}>
              <Descriptions bordered column={{ xs: 1, sm: 2, md: 4 }} size="small">
                <Descriptions.Item label="Status">
                  <Tag color={classData.status === 'active' ? 'green' : 'orange'} style={{ borderRadius: 6 }}>
                    {classData.status}
                  </Tag>
                </Descriptions.Item>
                <Descriptions.Item label="Sync Status">
                  <Tag style={{ borderRadius: 6 }}>{classData.sync_status || 'unknown'}</Tag>
                </Descriptions.Item>
                <Descriptions.Item label="Students">
                  <TeamOutlined /> {classData.total_students || 0}
                </Descriptions.Item>
                <Descriptions.Item label="Staff">
                  <UserOutlined /> {classData.total_staff || 0}
                </Descriptions.Item>
                <Descriptions.Item label="Created">
                  {classData.created_at ? new Date(classData.created_at).toLocaleDateString() : '-'}
                </Descriptions.Item>
                <Descriptions.Item label="Updated">
                  {classData.updated_at ? new Date(classData.updated_at).toLocaleDateString() : '-'}
                </Descriptions.Item>
              </Descriptions>
            </Card>
          </Col>
        </Row>
      ),
    },
  ];

  return (
    <div>
      <Breadcrumb
        items={[
          { title: <a onClick={() => navigate('/classes')} style={{ cursor: 'pointer' }}><HomeOutlined /> Classes</a> },
          { title: classData.course_code || `Class #${id}` },
        ]}
        style={{ marginBottom: 16 }}
      />

      <div style={{
        background: 'linear-gradient(135deg, #0891b2 0%, #06b6d4 100%)',
        borderRadius: 16,
        padding: '24px 28px',
        marginBottom: 24,
        position: 'relative',
        overflow: 'hidden',
      }}>
        <Row align="middle" justify="space-between" wrap gutter={[12, 12]}>
          <Col>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
              <Title level={4} style={{ margin: 0, color: '#fff' }}>
                {classData.course_code || 'N/A'} — {classData.course_title}
              </Title>
              <Tag color={classData.status === 'active' ? 'green' : 'orange'}
                style={{ borderRadius: 6, background: 'rgba(255,255,255,0.2)', border: '1px solid rgba(255,255,255,0.3)', color: '#fff' }}>
                {classData.status}
              </Tag>
            </div>
            <Text style={{ color: 'rgba(255,255,255,0.8)', fontSize: 13, marginTop: 4, display: 'block' }}>
              {classData.vu_session_name}{classData.semester_name ? ` · ${classData.semester_name}` : ''}
              {classData.department_name ? ` · ${classData.department_name}` : ''}
            </Text>
          </Col>
          <Col>
            <Space wrap>
              <Tooltip title="Refresh data">
                <Button icon={<ReloadOutlined />} onClick={fetchClass}
                  style={{ borderRadius: 8, background: 'rgba(255,255,255,0.15)', border: '1px solid rgba(255,255,255,0.3)', color: '#fff' }}>
                  Refresh
                </Button>
              </Tooltip>
              <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/classes')}
                style={{ borderRadius: 8, background: 'rgba(255,255,255,0.15)', border: '1px solid rgba(255,255,255,0.3)', color: '#fff' }}>
                Back
              </Button>
            </Space>
          </Col>
        </Row>
      </div>

      <Card styles={{ body: { padding: 0 } }} style={{ borderRadius: 12, overflow: 'hidden' }}>
        <Tabs
          defaultActiveKey="students"
          items={tabItems}
          style={{ padding: '0 16px' }}
        />
      </Card>

      <Modal
        title={<span><UserOutlined /> Assign Staff</span>}
        open={staffOpen}
        onOk={handleAssignStaff}
        onCancel={() => { setStaffOpen(false); setStaffId(null); setStaffRole('assistant'); setStaffSearchResults([]); }}
        confirmLoading={staffLoading}
        okText="Assign"
        okButtonProps={{ disabled: !staffId }}
        destroyOnClose
      >
        <Space direction="vertical" style={{ width: '100%' }}>
          <Paragraph type="secondary">
            Search for staff by name, email, or phone number, then select their role.
          </Paragraph>
          <Form layout="vertical">
            <Form.Item label="Search Staff" required>
              <Select
                showSearch
                placeholder="Type name, email, or phone..."
                value={staffId}
                onSearch={handleStaffSearch}
                onChange={(val) => setStaffId(val)}
                loading={staffSearching}
                filterOption={false}
                notFoundContent={staffSearching ? <Spin size="small" /> : 'No staff found'}
                style={{ width: '100%' }}
                autoFocus
              >
                {staffSearchResults.map((s: any) => (
                  <Select.Option key={s.id} value={s.id}>
                    <Space>
                      <span style={{ fontWeight: 500 }}>{s.lname} {s.fname}</span>
                      <span style={{ color: '#94a3b8', fontSize: 12 }}>{s.email}</span>
                      <Tag style={{ fontSize: 10, lineHeight: '18px', borderRadius: 4 }}>
                        {s.department_name || '—'}
                      </Tag>
                    </Space>
                  </Select.Option>
                ))}
                {staffSearchResults.length > 0 && (
                  <Select.Option key="__count__" value={-1} disabled>
                    <Text type="secondary" style={{ fontSize: 11 }}>
                      {staffSearchResults.length} result{staffSearchResults.length !== 1 ? 's' : ''}
                    </Text>
                  </Select.Option>
                )}
              </Select>
            </Form.Item>
            <Form.Item label="Role">
              <Select
                value={staffRole}
                onChange={setStaffRole}
                options={[
                  { value: 'primary', label: 'Primary Instructor' },
                  { value: 'assistant', label: 'Assistant' },
                  { value: 'guest', label: 'Guest Lecturer' },
                ]}
              />
            </Form.Item>
          </Form>
        </Space>
      </Modal>
    </div>
  );
}
