import { useState, useEffect } from 'react';
import { Card, Typography, Spin, Empty, Button, Space, Tag, Progress, message, Descriptions } from 'antd';
import { ArrowLeftOutlined, CheckCircleOutlined, PlayCircleOutlined, FileTextOutlined } from '@ant-design/icons';
import { useParams, useNavigate } from 'react-router-dom';
import { api } from '../api/client';

const { Title, Paragraph } = Typography;

export default function LessonView() {
  const { courseId, lessonId } = useParams();
  const navigate = useNavigate();
  const [lesson, setLesson] = useState<any>(null);
  const [attachments, setAttachments] = useState<any[]>([]);
  const [progress, setProgress] = useState<any>(null);
  const [marking, setMarking] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!lessonId) return;
    setLoading(true);
    Promise.all([
      api.get(`/lessons/${lessonId}`).catch(() => null),
      api.get(`/lessons/${lessonId}/attachments`).catch(() => ([] as any[])),
      api.get(`/lessons/${lessonId}/progress`).catch(() => null),
    ]).then(([l, a, p]) => {
      setLesson(l);
      setAttachments(a || []);
      setProgress(p);
    }).finally(() => setLoading(false));
  }, [lessonId]);

  const handleMarkComplete = async () => {
    if (!lessonId) return;
    setMarking(true);
    try {
      const res = await api.post(`/lessons/${lessonId}/progress`, { status: 'completed', progress_percent: 100 });
      setProgress(res);
      message.success('Lesson marked as complete');
    } catch {
      message.error('Failed to update progress');
    } finally {
      setMarking(false);
    }
  };

  if (loading) return <div style={{ textAlign: 'center', padding: 80 }}><Spin size="large" /></div>;

  if (!lesson) {
    return (
      <div>
        <Button icon={<ArrowLeftOutlined />} onClick={() => navigate(-1)} style={{ marginBottom: 16 }}>Back</Button>
        <Card><Empty description="Lesson not found" /></Card>
      </div>
    );
  }

  return (
    <div>
      <Button icon={<ArrowLeftOutlined />} onClick={() => navigate(`/courses/${courseId}`)} style={{ marginBottom: 16 }}>Back to Course</Button>

      <Card style={{ marginBottom: 16 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
          <div>
            <Title level={4} style={{ margin: 0 }}>{lesson.title}</Title>
            {lesson.type && <Tag style={{ marginTop: 8 }}>{lesson.type}</Tag>}
          </div>
          {progress?.status === 'completed' ? (
            <Tag color="success" icon={<CheckCircleOutlined />}>Completed</Tag>
          ) : (
            <Button type="primary" icon={<CheckCircleOutlined />} loading={marking} onClick={handleMarkComplete}>
              Mark as Complete
            </Button>
          )}
        </div>
      </Card>

      {lesson.description && (
        <Card title="Description" style={{ marginBottom: 16 }}>
          <Paragraph>{lesson.description}</Paragraph>
        </Card>
      )}

      {lesson.content && (
        <Card title="Content" style={{ marginBottom: 16 }}>
          <div dangerouslySetInnerHTML={{ __html: lesson.content }} />
        </Card>
      )}

      {lesson.video_url && (
        <Card title="Video" style={{ marginBottom: 16 }}>
          <div style={{ position: 'relative', paddingBottom: '56.25%', height: 0 }}>
            <iframe
              src={lesson.video_url}
              style={{ position: 'absolute', top: 0, left: 0, width: '100%', height: '100%', border: 'none' }}
              allowFullScreen
              title="Lesson Video"
            />
          </div>
        </Card>
      )}

      {lesson.duration_minutes && (
        <Card style={{ marginBottom: 16 }}>
          <Descriptions size="small" column={3}>
            <Descriptions.Item label="Duration">{lesson.duration_minutes} min</Descriptions.Item>
            <Descriptions.Item label="Order">{lesson.sort_order}</Descriptions.Item>
            {progress && (
              <Descriptions.Item label="Progress">
                <Progress percent={Math.round(progress.progress_percent || 0)} size="small" style={{ width: 100 }} />
              </Descriptions.Item>
            )}
          </Descriptions>
        </Card>
      )}

      {attachments.length > 0 && (
        <Card title={`Attachments (${attachments.length})`}>
          {attachments.map((att: any) => (
            <Card.Grid
              key={att.id}
              style={{ width: '25%', cursor: 'pointer', textAlign: 'center' }}
              onClick={() => att.file_url && window.open(att.file_url, '_blank')}
            >
              <FileTextOutlined style={{ fontSize: 32, color: '#4f46e5', marginBottom: 8 }} />
              <div style={{ fontSize: 13 }}>{att.file_name || 'Attachment'}</div>
              {att.file_size && <div style={{ fontSize: 11, color: '#888' }}>{(att.file_size / 1024).toFixed(0)} KB</div>}
            </Card.Grid>
          ))}
        </Card>
      )}
    </div>
  );
}
