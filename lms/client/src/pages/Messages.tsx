import { useState, useEffect, useRef } from 'react';
import { Row, Col, Card, List, Input, Button, Typography, Spin, Empty, message, Avatar, Badge } from 'antd';
import { SendOutlined, UserOutlined } from '@ant-design/icons';
import { api } from '../api/client';

const { Text, Title } = Typography;

export default function Messages() {
  const [conversations, setConversations] = useState<any[]>([]);
  const [activeConv, setActiveConv] = useState<any>(null);
  const [messages, setMessages] = useState<any[]>([]);
  const [msgText, setMsgText] = useState('');
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const msgEndRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    api.get('/messages/conversations').then((res: any) => {
      setConversations(res.conversations || []);
    }).catch(() => {}).finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    msgEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const openConversation = async (convId: number) => {
    const conv = conversations.find((c) => c.id === convId);
    setActiveConv(conv);
    try {
      const res = await api.get(`/messages/conversations/${convId}`) as any;
      setMessages(res.items || []);
    } catch {
      setMessages([]);
    }
  };

  const sendMessage = async () => {
    if (!msgText.trim() || !activeConv) return;
    setSending(true);
    try {
      await api.post('/messages/send', { conversation_id: activeConv.id, message: msgText });
      setMsgText('');
      openConversation(activeConv.id);
    } catch (e: any) {
      message.error(e.message);
    } finally {
      setSending(false);
    }
  };

  return (
    <Row gutter={16} style={{ height: 'calc(100vh - 140px)' }}>
      <Col xs={24} md={8}>
        <Card title="Conversations" style={{ height: '100%' }} bodyStyle={{ overflow: 'auto', height: 'calc(100% - 57px)', padding: 0 }}>
          {loading ? (
            <div style={{ textAlign: 'center', padding: 40 }}><Spin /></div>
          ) : conversations.length === 0 ? (
            <Empty description="No conversations" style={{ padding: 40 }} />
          ) : (
            <List
              dataSource={conversations}
              renderItem={(c: any) => (
                <List.Item
                  onClick={() => openConversation(c.id)}
                  style={{ cursor: 'pointer', padding: '12px 16px', background: activeConv?.id === c.id ? '#f0f5ff' : undefined }}
                >
                  <List.Item.Meta
                    avatar={<Badge count={c.unread_count || 0} size="small"><Avatar icon={<UserOutlined />} /></Badge>}
                    title={<Text strong>{c.subject || 'Conversation'}</Text>}
                    description={
                      <div>
                        <Text type="secondary" ellipsis style={{ fontSize: 12 }}>{c.last_message}</Text>
                        <br />
                        <Text type="secondary" style={{ fontSize: 11 }}>
                          {c.last_message_at ? new Date(c.last_message_at).toLocaleDateString() : ''}
                        </Text>
                      </div>
                    }
                  />
                </List.Item>
              )}
            />
          )}
        </Card>
      </Col>

      <Col xs={24} md={16}>
        <Card
          title={activeConv ? activeConv.subject || 'Conversation' : 'Select a conversation'}
          style={{ height: '100%', display: 'flex', flexDirection: 'column' }}
          bodyStyle={{ flex: 1, display: 'flex', flexDirection: 'column', padding: 0, overflow: 'hidden' }}
        >
          {!activeConv ? (
            <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Empty description="Select a conversation to start messaging" />
            </div>
          ) : (
            <>
              <div style={{ flex: 1, overflow: 'auto', padding: 16 }}>
                {messages.length === 0 ? (
                  <Empty description="No messages yet" />
                ) : (
                  messages.map((m: any, i: number) => (
                    <div
                      key={m.id || i}
                      style={{
                        display: 'flex',
                        justifyContent: 'flex-start',
                        marginBottom: 12,
                      }}
                    >
                      <div style={{
                        maxWidth: '70%',
                        padding: '8px 12px',
                        borderRadius: 8,
                        background: '#f0f0f0',
                      }}>
                        <Text strong style={{ fontSize: 12 }}>{m.sender?.first_name || 'User'}</Text>
                        <div>{m.message}</div>
                        <Text type="secondary" style={{ fontSize: 11 }}>{new Date(m.created_at).toLocaleString()}</Text>
                      </div>
                    </div>
                  ))
                )}
                <div ref={msgEndRef} />
              </div>
              <div style={{ borderTop: '1px solid #f0f0f0', padding: 12, display: 'flex', gap: 8 }}>
                <Input
                  value={msgText}
                  onChange={(e) => setMsgText(e.target.value)}
                  onPressEnter={sendMessage}
                  placeholder="Type a message..."
                  disabled={sending}
                />
                <Button type="primary" icon={<SendOutlined />} onClick={sendMessage} loading={sending}>
                  Send
                </Button>
              </div>
            </>
          )}
        </Card>
      </Col>
    </Row>
  );
}
