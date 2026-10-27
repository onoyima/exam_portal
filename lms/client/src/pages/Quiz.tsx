import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Card, Typography, Button, Spin, Tag, Radio, Checkbox, Input, Statistic, Row, Col, message, Modal, Breadcrumb } from 'antd';
import { ClockCircleOutlined, QuestionCircleOutlined } from '@ant-design/icons';
import { api } from '../api/client';

const { Title, Text } = Typography;
const { TextArea } = Input;

export default function Quiz() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [quiz, setQuiz] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [attempt, setAttempt] = useState<any>(null);
  const [timeLeft, setTimeLeft] = useState(0);
  const timerRef = useRef<any>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (!id) return;
    api.get(`/quizzes/${id}`).then((res: any) => {
      setQuiz(res.quiz);
    }).catch(() => message.error('Failed to load quiz')).finally(() => setLoading(false));
    return () => { if (timerRef.current) clearInterval(timerRef.current); };
  }, [id]);

  useEffect(() => {
    if (timeLeft <= 0 && timerRef.current) {
      clearInterval(timerRef.current);
      if (attempt) handleSubmit();
    }
  }, [timeLeft]);

  const startAttempt = async () => {
    try {
      const res = await api.post(`/quizzes/${id}/attempts`) as any;
      setAttempt(res.attempt);
      if (quiz?.time_limit_minutes) {
        setTimeLeft(quiz.time_limit_minutes * 60);
        timerRef.current = setInterval(() => setTimeLeft((t) => t - 1), 1000);
      }
    } catch (e: any) {
      message.error(e.message);
    }
  };

  const handleSubmit = async () => {
    if (!id || !attempt) return;
    setSubmitting(true);
    try {
      const form = document.getElementById('quizForm') as HTMLFormElement;
      const formData = new FormData(form);
      const answers: any[] = [];
      const qmap: Record<string, string[]> = {};
      formData.forEach((value, key) => {
        const qid = key.replace('q_', '');
        if (!qmap[qid]) qmap[qid] = [];
        qmap[qid].push(value as string);
      });
      Object.entries(qmap).forEach(([qid, vals]) => {
        answers.push({ question_id: parseInt(qid), answer: vals.length === 1 ? vals[0] : vals, time_spent: 0 });
      });
      const res = await api.post(`/quizzes/${id}/attempts/${attempt.id}/submit`, { answers }) as any;
      Modal.success({
        title: 'Attempt Submitted',
        content: `Score: ${res.attempt?.score || 0}/${res.attempt?.max_score || 0} (${res.attempt?.percentage || 0}%)`,
        onOk: () => { setAttempt(null); navigate(`/quiz/${id}`); },
      });
    } catch (e: any) {
      message.error(e.message);
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) return <div style={{ textAlign: 'center', padding: 80 }}><Spin size="large" /></div>;
  if (!quiz) return <Card><Title level={4}>Quiz not found</Title></Card>;

  if (attempt) {
    const questions = quiz.questions || [];
    const minutes = Math.floor(timeLeft / 60);
    const seconds = timeLeft % 60;

    return (
      <div>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
          <Title level={4} style={{ margin: 0 }}>{quiz.title} — Attempt {attempt.attempt_number}</Title>
          {quiz.time_limit_minutes && (
            <div style={{ color: timeLeft < 60 ? 'red' : undefined, fontWeight: 'bold' }}>
              <ClockCircleOutlined /> {minutes}:{seconds.toString().padStart(2, '0')}
            </div>
          )}
        </div>
        <form id="quizForm">
          {questions.map((q: any, i: number) => (
            <Card key={q.id} style={{ marginBottom: 16 }} title={<span>{i + 1}. {q.question_text} <Tag style={{ float: 'right' }}>{q.points} pts</Tag></span>}>
              {q.options?.length ? (
                q.question_type === 'multiple_answer' ? (
                  <Checkbox.Group>
                    {q.options.map((opt: any, j: number) => (
                      <div key={j} style={{ marginBottom: 8 }}>
                        <Checkbox value={opt.value || opt}>{opt.label || opt}</Checkbox>
                      </div>
                    ))}
                  </Checkbox.Group>
                ) : (
                  <Radio.Group>
                    {q.options.map((opt: any, j: number) => (
                      <div key={j} style={{ marginBottom: 8 }}>
                        <Radio value={opt.value || opt}>{opt.label || opt}</Radio>
                      </div>
                    ))}
                  </Radio.Group>
                )
              ) : (
                <TextArea name={`q_${q.id}`} rows={3} placeholder="Enter your answer..." />
              )}
            </Card>
          ))}
          <Button type="primary" size="large" htmlType="button" onClick={handleSubmit} loading={submitting} block>
            Submit Attempt
          </Button>
        </form>
      </div>
    );
  }

  return (
    <div>
      <Breadcrumb items={[
        { title: <a onClick={() => navigate('/courses')}>Courses</a> },
        { title: quiz.title },
      ]} style={{ marginBottom: 16 }} />

      <Card>
        <Title level={3}>{quiz.title}</Title>
        <Tag>{quiz.quiz_type}</Tag>
        {quiz.description && <Text style={{ display: 'block', marginTop: 12 }}>{quiz.description}</Text>}

        <Row gutter={[16, 16]} style={{ marginTop: 24 }}>
          <Col span={6}><Statistic title="Questions" value={quiz.question_count || 0} prefix={<QuestionCircleOutlined />} /></Col>
          <Col span={6}><Statistic title="Total Points" value={quiz.total_points || 0} /></Col>
          <Col span={6}><Statistic title="Remaining Attempts" value={quiz.remaining_attempts || 0} /></Col>
          <Col span={6}><Statistic title="Time Limit" value={quiz.time_limit_minutes ? `${quiz.time_limit_minutes}m` : '--'} prefix={<ClockCircleOutlined />} /></Col>
        </Row>

        {quiz.remaining_attempts > 0 ? (
          <Button type="primary" size="large" style={{ marginTop: 24 }} onClick={startAttempt}>
            Start Attempt
          </Button>
        ) : (
          <Text type="danger" style={{ display: 'block', marginTop: 16 }}>No attempts remaining</Text>
        )}
      </Card>

      {quiz.attempts?.length > 0 && (
        <Card title="Your Attempts" style={{ marginTop: 24 }}>
          {quiz.attempts.map((a: any) => (
            <div key={a.id} style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: '1px solid #f0f0f0' }}>
              <span>Attempt {a.attempt_number}</span>
              <span>{a.score || '--'}/{a.max_score || '--'} ({a.percentage || '--'}%)</span>
              <Tag color={a.passed === 1 ? 'green' : a.passed === 0 ? 'red' : 'default'}>
                {a.passed === 1 ? 'Passed' : a.passed === 0 ? 'Failed' : '--'}
              </Tag>
            </div>
          ))}
        </Card>
      )}
    </div>
  );
}
