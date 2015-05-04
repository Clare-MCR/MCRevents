def sendEmail(addrTo, subject, body, cc='', addrFrom='mcr-socsec@clare.cam.ac.uk', replyto='mcr-socsec@clare.cam.ac.uk'):
  message = _createEmail(subject, addrTo, body, replyto, cc)
  addrTo = [addrTo] + [cc]
  _sendEmail(addrFrom, addrTo, message)


def _createEmail(subject, to, body, replyto, cc):
  import email
  msg = email.Message.Message()
  msg.add_header('Subject', subject)
  msg.add_header('Reply-To', replyto)
  msg.add_header('CC',cc)
  msg.add_header('To', to)
  msg.add_header('Content-type', 'text/plain;charset=ISO-8859-1;format=flowed')
  msg.set_payload(body)
  return msg

def _sendEmail(frm, to, message):
  import smtplib
  server = smtplib.SMTP('localhost')
  server.sendmail(frm, to, message.as_string())
  server.quit()

