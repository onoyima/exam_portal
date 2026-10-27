import { useState, useEffect } from 'react';
import { Row, Col, Card, Spin, Tag, Progress, Typography, Empty, Alert, Button } from 'antd';
import { ReloadOutlined, BookOutlined, ReadOutlined, UserOutlined } from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';
import { api } from '../api/client';
import { useAuth } from '../contexts/AuthContext';

const { Title, Text } = Typography;

interface Course {
  id: number;
  uuid: string;
  title: string;
  code: string;
  level: string;
  credits: number;
  status: string;
  short_description: string;
  enrollment_status?: string;
  progress_percent?: number;
  enrolled_at?: string;
}

export default function MyCourses() {
  const navigate = useNavigate();
  const { user } = useAuth();
  const role = user?.role || user?.portal_user_type || '';
  const isStudent = role === 'student';
  const isStaff = role === 'examiner' || role === 'instructor' || role === 'ta';

  const [courses, setCourses] = useState<Course[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true);
    setError(null);
    api.get('/courses/my').then((res: any) => {
      console.log('[MyCourses] /courses/my response:', JSON.stringify(res, null, 2));
      console.log('[MyCourses] first course keys:', res.courses?.[0] ? Object.keys(res.courses[0]) : 'no courses');
      setCourses(res.courses || []);
    }).catch((err: any) => {
      setError(err?.message || 'Failed to load your courses');
    }).finally(() => setLoading(false));
  }, []);

  const pageTitle = isStudent ? 'My Enrolled Courses' : isStaff ? 'My Courses' : 'My Courses';

  return (
    <div>
      <div style={{ marginBottom: 24 }}>
        <Title level={4} style={{ margin: 0 }}>{pageTitle}</Title>
        <Text type="secondary">{isStudent ? 'Courses you are currently enrolled in' : 'Courses assigned to you'}</Text>
      </div>

      {error && (
        <Alert
          message="Failed to load your courses"
          description={error}
          type="error"
          showIcon
          style={{ marginBottom: 16 }}
          action={<Button size="small" danger onClick={() => window.location.reload()} icon={<ReloadOutlined />}>Retry</Button>}
          closable
        />
      )}

      {loading ? (
        <Row gutter={[16, 16]}>
          {Array.from({ length: 4 }).map((_, i) => (
            <Col xs={24} sm={12} lg={8} xl={6} key={i}>
              <Card><Spin /></Card>
            </Col>
          ))}
        </Row>
      ) : courses.length === 0 ? (
        <Card>
          <Empty
            description={isStudent ? 'You are not enrolled in any courses yet' : 'No courses assigned to you yet'}
            image={Empty.PRESENTED_IMAGE_SIMPLE}
          />
        </Card>
      ) : (
        <>
          <div style={{ marginBottom: 12, color: '#888' }}>
            Showing {courses.length} course{courses.length !== 1 ? 's' : ''}
          </div>
          <Row gutter={[16, 16]}>
            {courses.map((c) => (
              <Col xs={24} sm={12} lg={8} xl={6} key={c.id}>
                <Card
                  hoverable
                  onClick={() => c.uuid ? navigate(`/courses/${c.uuid}`) : null}
                  style={{ borderRadius: 8, height: '100%', display: 'flex', flexDirection: 'column' }}
                  styles={{ body: { flex: 1, display: 'flex', flexDirection: 'column' } }}
                >
                  <div style={{ flex: 1 }}>
                    <div style={{ marginBottom: 12 }}>
                      {isStaff ? (
                        <ReadOutlined style={{ fontSize: 28, color: '#0891b2' }} />
                      ) : (
                        <BookOutlined style={{ fontSize: 28, color: '#4f46e5' }} />
                      )}
                    </div>
                    <Title level={5} style={{ margin: 0, fontSize: 14 }} ellipsis={{ rows: 2 }}>
                      {c.title}
                    </Title>
                    <div style={{ marginTop: 4 }}>
                      <Text type="secondary" style={{ fontSize: 12 }}>
                        {c.code || ''}{c.level ? ` · ${c.level}` : ''}
                      </Text>
                    </div>
                    {c.short_description && (
                      <div style={{ marginTop: 8, fontSize: 12, color: '#666', lineHeight: 1.5 }}>
                        {c.short_description.substring(0, 120)}
                        {c.short_description.length > 120 ? '...' : ''}
                      </div>
                    )}
                    {isStudent && typeof c.progress_percent === 'number' && (
                      <div style={{ marginTop: 12 }}>
                        <Progress percent={Math.round(c.progress_percent)} size="small" />
                      </div>
                    )}
                  </div>
                  <div style={{ marginTop: 12, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div>
                      <Text type="secondary" style={{ fontSize: 12 }}>{c.credits ?? 3} Credits</Text>
                      {isStudent && (
                        <Tag color={c.enrollment_status === 'active' ? 'green' : 'orange'} style={{ marginLeft: 6, fontSize: 11 }}>
                          {c.enrollment_status || 'enrolled'}
                        </Tag>
                      )}
                    </div>
                    <Tag color={c.status === 'published' ? 'green' : c.status === 'draft' ? 'orange' : 'default'} style={{ fontSize: 11 }}>
                      {c.status || 'unknown'}
                    </Tag>
                  </div>
                </Card>
              </Col>
            ))}
          </Row>
        </>
      )}
    </div>
  );
}
