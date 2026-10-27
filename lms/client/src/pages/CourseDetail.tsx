import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Card, Typography, Button, Spin, Tag, Collapse, message, Breadcrumb, Empty, Row, Col, Progress, Space, Tooltip } from 'antd';
import { BookOutlined, PlayCircleOutlined, FileTextOutlined, VideoCameraOutlined, QuestionCircleOutlined, CheckCircleOutlined, ClockCircleOutlined, ArrowLeftOutlined, LockOutlined, TrophyOutlined } from '@ant-design/icons';
import { api } from '../api/client';

const { Title, Text, Paragraph } = Typography;

const contentIcons: Record<string, React.ReactNode> = {
  video: <VideoCameraOutlined style={{ color: '#0891b2' }} />,
  document: <FileTextOutlined style={{ color: '#6366f1' }} />,
  quiz: <QuestionCircleOutlined style={{ color: '#f59e0b' }} />,
  text: <FileTextOutlined style={{ color: '#64748b' }} />,
  assignment: <BookOutlined style={{ color: '#16a34a' }} />,
  discussion: <PlayCircleOutlined style={{ color: '#8b5cf6' }} />,
};

const lessonTypeColors: Record<string, string> = {
  video: '#e0f2fe',
  document: '#eef2ff',
  quiz: '#fef3c7',
  text: '#f1f5f9',
  assignment: '#dcfce7',
  discussion: '#f3e8ff',
};

const totalDuration = (lessons: any[]) =>
  lessons.reduce((s: number, l: any) => s + (l.duration_minutes || 0), 0);

const formatDuration = (min: number) => {
  if (min < 60) return `${min} min`;
  return `${Math.floor(min / 60)}h ${min % 60}m`;
};

export default function CourseDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [course, setCourse] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [enrolling, setEnrolling] = useState(false);
  const [enrolled, setEnrolled] = useState(false);
  const [expandedModules, setExpandedModules] = useState<string[]>([]);

  useEffect(() => {
    if (!id) return;
    api.get(`/courses/${id}`).then((res: any) => {
      console.log('[CourseDetail] /courses/:id response keys:', Object.keys(res));
      console.log('[CourseDetail] course keys:', res.course ? Object.keys(res.course) : 'no course');
      console.log('[CourseDetail] course sample:', JSON.stringify(res.course, null, 2));
      setCourse(res.course);
    }).catch((err: any) => {
      console.error('[CourseDetail] fetch error:', err?.message);
      message.error('Failed to load course');
    }).finally(() => setLoading(false));
  }, [id]);

  const handleEnroll = async () => {
    if (!course) return;
    setEnrolling(true);
    try {
      await api.post('/enrollments', { course_id: course.id });
      message.success('Enrolled successfully!');
      setEnrolled(true);
    } catch (e: any) {
      message.error(e.message);
    } finally {
      setEnrolling(false);
    }
  };

  if (loading) return (
    <div style={{ textAlign: 'center', padding: 100 }}>
      <Spin size="large" />
      <div style={{ marginTop: 16, color: '#64748b' }}>Loading course...</div>
    </div>
  );
  if (!course) return <Card style={{ borderRadius: 12, marginTop: 24 }}><Empty description="Course not found" /></Card>;

  const modules = course.modules || [];
  const allLessons = modules.flatMap((m: any) => m.lessons || []);
  const totalLessons = allLessons.length;
  const completedLessons = allLessons.filter((l: any) => l.completed || l.progress_percent === 100).length;
  const overallProgress = totalLessons > 0 ? Math.round((completedLessons / totalLessons) * 100) : 0;

  const collapseItems = modules.map((m: any, i: number) => {
    const lessons = m.lessons || [];
    const modCompleted = lessons.filter((l: any) => l.completed || l.progress_percent === 100).length;
    const modProgress = lessons.length > 0 ? Math.round((modCompleted / lessons.length) * 100) : 0;
    const dur = totalDuration(lessons);
    return {
      key: String(m.id || i),
      label: (
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: '100%', paddingRight: 8 }}>
          <Space>
            <div style={{
              width: 28, height: 28, borderRadius: 8, display: 'flex', alignItems: 'center', justifyContent: 'center',
              background: modProgress === 100 ? '#dcfce7' : '#f1f5f9', fontSize: 13, fontWeight: 600, color: modProgress === 100 ? '#16a34a' : '#64748b',
            }}>
              {modProgress === 100 ? <CheckCircleOutlined style={{ color: '#16a34a' }} /> : i + 1}
            </div>
            <div>
              <Text strong style={{ fontSize: 14, color: '#0f172a' }}>{m.title}</Text>
              {m.description && (
                <Text style={{ fontSize: 12, color: '#64748b', display: 'block' }} ellipsis>{m.description}</Text>
              )}
            </div>
          </Space>
          <Space size={8}>
            <Text style={{ fontSize: 12, color: '#94a3b8' }}>
              {lessons.length} lesson{lessons.length !== 1 ? 's' : ''}
              {dur > 0 ? ` · ${formatDuration(dur)}` : ''}
            </Text>
            {lessons.length > 0 && (
              <Progress type="circle" percent={modProgress} size={24} strokeColor={modProgress === 100 ? '#16a34a' : '#0891b2'} />
            )}
          </Space>
        </div>
      ),
      children: (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          {lessons.length === 0 ? (
            <Text type="secondary" style={{ padding: '8px 0', fontSize: 13 }}>
              No lessons in this module yet
            </Text>
          ) : (
            lessons.map((l: any, li: number) => {
              const isComplete = l.completed || l.progress_percent === 100;
              const typeColor = lessonTypeColors[l.content_type] || '#f1f5f9';
              return (
                <div
                  key={l.id}
                  onClick={() => {
                    if (l.content_type === 'quiz' && l.quiz_id) {
                      navigate(`/quiz/${l.quiz_id}`);
                    } else {
                      navigate(`/courses/${course.uuid || course.id}/lessons/${l.id}`);
                    }
                  }}
                  style={{
                    display: 'flex', alignItems: 'center', gap: 12, padding: '10px 12px',
                    borderRadius: 10, cursor: 'pointer', transition: 'all 0.15s',
                    background: isComplete ? '#f0fdf4' : '#fafafa',
                    border: `1px solid ${isComplete ? '#bbf7d0' : '#f1f5f9'}`,
                  }}
                  onMouseEnter={(e) => { e.currentTarget.style.borderColor = '#0891b2'; e.currentTarget.style.background = '#f0f9ff'; }}
                  onMouseLeave={(e) => { e.currentTarget.style.borderColor = isComplete ? '#bbf7d0' : '#f1f5f9'; e.currentTarget.style.background = isComplete ? '#f0fdf4' : '#fafafa'; }}
                >
                  <div style={{
                    width: 34, height: 34, borderRadius: 8, display: 'flex', alignItems: 'center', justifyContent: 'center',
                    background: typeColor, flexShrink: 0,
                  }}>
                    {isComplete ? <CheckCircleOutlined style={{ color: '#16a34a', fontSize: 16 }} /> : (contentIcons[l.content_type] || <FileTextOutlined />)}
                  </div>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <Text strong style={{ fontSize: 13, color: '#0f172a', display: 'block' }} ellipsis>
                      {l.title}
                      {l.required && <Tag color="red" style={{ marginLeft: 6, fontSize: 10, lineHeight: '16px' }}>Required</Tag>}
                    </Text>
                    <Space size={12}>
                      <Tag style={{ fontSize: 10, borderRadius: 4, margin: 0, lineHeight: '18px', background: typeColor, border: 'none', color: '#475569' }}>
                        {l.content_type || 'lesson'}
                      </Tag>
                      {l.duration_minutes > 0 && (
                        <Text style={{ fontSize: 11, color: '#94a3b8' }}>
                          <ClockCircleOutlined style={{ marginRight: 2 }} />{formatDuration(l.duration_minutes)}
                        </Text>
                      )}
                    </Space>
                  </div>
                  <div style={{ flexShrink: 0 }}>
                    {l.progress_percent !== undefined && l.progress_percent > 0 && l.progress_percent < 100 && (
                      <Progress type="circle" percent={Math.round(l.progress_percent)} size={28} strokeColor="#0891b2" />
                    )}
                    {isComplete && (
                      <Tag color="success" style={{ borderRadius: 6, fontSize: 11 }}>Done</Tag>
                    )}
                    {(!l.progress_percent || l.progress_percent === 0) && !isComplete && (
                      <Tag style={{ borderRadius: 6, fontSize: 11, background: '#f1f5f9', border: 'none', color: '#64748b' }}>
                        Start
                      </Tag>
                    )}
                  </div>
                </div>
              );
            })
          )}
        </div>
      ),
    };
  });

  return (
    <div>
      <Breadcrumb
        items={[
          { title: <a onClick={() => navigate('/courses')} style={{ cursor: 'pointer' }}>Courses</a> },
          { title: course.title },
        ]}
        style={{ marginBottom: 16 }}
      />

      <div style={{
        background: 'linear-gradient(135deg, #0891b2 0%, #06b6d4 50%, #0e7490 100%)',
        borderRadius: 16,
        padding: '28px 32px',
        marginBottom: 24,
        position: 'relative',
        overflow: 'hidden',
      }}>
        <div style={{ position: 'absolute', top: -20, right: -20, opacity: 0.06, fontSize: 120 }}>
          <BookOutlined />
        </div>
        <Row align="middle" justify="space-between" wrap gutter={[16, 16]}>
          <Col xs={24} md={16}>
            <Title level={3} style={{ margin: 0, color: '#fff', fontSize: 24 }}>{course.title}</Title>
            <Space style={{ marginTop: 10 }} wrap>
              <Tag style={{ borderRadius: 6, background: 'rgba(255,255,255,0.15)', border: '1px solid rgba(255,255,255,0.3)', color: '#fff' }}>
                {course.code || 'N/A'}
              </Tag>
              <Tag style={{ borderRadius: 6, background: 'rgba(255,255,255,0.15)', border: '1px solid rgba(255,255,255,0.3)', color: '#fff' }}>
                {course.level || 'All levels'}
              </Tag>
              <Tag style={{ borderRadius: 6, background: 'rgba(255,255,255,0.15)', border: '1px solid rgba(255,255,255,0.3)', color: '#fff' }}>
                {course.credits || 0} Credits
              </Tag>
              <Tag style={{ borderRadius: 6, background: 'rgba(255,255,255,0.15)', border: '1px solid rgba(255,255,255,0.3)', color: '#fff' }}>
                {modules.length} modules · {totalLessons} lessons
              </Tag>
            </Space>
            {course.short_description && (
              <Paragraph style={{ color: 'rgba(255,255,255,0.85)', marginTop: 12, marginBottom: 0, maxWidth: 600, fontSize: 13 }}>
                {course.short_description}
              </Paragraph>
            )}
          </Col>
          <Col xs={24} md={6} style={{ textAlign: 'right' }}>
            <Button
              type="primary"
              size="large"
              icon={enrolled ? <CheckCircleOutlined /> : <BookOutlined />}
              loading={enrolling}
              onClick={handleEnroll}
              disabled={enrolled}
              style={{
                borderRadius: 10, height: 44, fontWeight: 600,
                background: enrolled ? '#16a34a' : '#fff',
                borderColor: 'transparent',
                color: enrolled ? '#fff' : '#0891b2',
                boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
              }}
            >
              {enrolled ? 'Enrolled' : 'Enroll Now'}
            </Button>
          </Col>
        </Row>
      </div>

      {overallProgress > 0 && (
        <Card size="small" style={{ marginBottom: 16, borderRadius: 10, border: '1px solid #e2e8f0' }}>
          <Row align="middle" gutter={16}>
            <Col xs={24} sm={6}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <TrophyOutlined style={{ fontSize: 20, color: '#f59e0b' }} />
                <div>
                  <Text style={{ fontSize: 12, color: '#64748b', display: 'block' }}>Overall Progress</Text>
                  <Text strong style={{ fontSize: 18 }}>{overallProgress}%</Text>
                </div>
              </div>
            </Col>
            <Col xs={24} sm={18}>
              <Progress
                percent={overallProgress}
                strokeColor={{ '0%': '#0891b2', '100%': '#06b6d4' }}
                trailColor="#e2e8f0"
                format={() => `${completedLessons}/${totalLessons} lessons`}
                style={{ margin: 0 }}
              />
            </Col>
          </Row>
        </Card>
      )}

      <div style={{ marginBottom: 12, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Title level={5} style={{ margin: 0 }}>Course Content</Title>
        <Text style={{ fontSize: 12, color: '#94a3b8' }}>
          {modules.length} module{modules.length !== 1 ? 's' : ''} · {totalLessons} lesson{totalLessons !== 1 ? 's' : ''}
          {totalLessons > 0 && ` · ${formatDuration(totalDuration(allLessons))} total`}
        </Text>
      </div>

      {modules.length === 0 ? (
        <Card style={{ borderRadius: 12 }}>
          <Empty description="No course content has been added yet" image={Empty.PRESENTED_IMAGE_SIMPLE} />
        </Card>
      ) : (
        <Collapse
          items={collapseItems}
          defaultActiveKey={modules.length > 0 ? [String(modules[0]?.id || 0)] : []}
          onChange={(keys) => setExpandedModules(keys as string[])}
          style={{ background: 'transparent', border: 'none' }}
          expandIconPosition="end"
          size="large"
        />
      )}
    </div>
  );
}
