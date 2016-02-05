<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title></title>
  </head>
  <body>
    <center>
      <table width="620" border="0" cellpadding="0" cellspacing="0" id="crm-event_receipt" style="font-family: Arial, Verdana, sans-serif; text-align: left;">
        <tr>
          <td>
            <strong>Dear {contact.display_name},</strong>

            <p>You are confirmed to volunteer for the following project(s):</p>

            <table>
              {foreach from=$volunteer_projects item=volunteer_project}
              <tr>
                <td>
                  <table>
                    <tr>
                      <td><strong>Project:</strong> {$volunteer_project.title}</td>
                    </tr>

                    {if !empty($volunteer_project.description)}
                    <tr>
                      <td><strong>Description:</strong> {$volunteer_project.description}</td>
                    </tr>
                    {/if}

                    {if !empty($volunteer_project.location)}
                    <tr>
                      <td>
                        <strong>Location:</strong>
                        <table>
                          <tr>
                            <td>{$volunteer_project.location.address.street_address}</td>
                          </tr>
                          <tr>
                            <td>{$volunteer_project.location.address.city}</td>
                          </tr>
                          <tr>
                            <td>{$volunteer_project.location.email}</td>
                          </tr>
                          <tr>
                            <td>{$volunteer_project.location.phone}</td>
                          </tr>
                        </table>
                      </td>
                    </tr>
                    {/if}

                    <tr>
                      <td>
                        <strong>Your Contact:</strong>
                        <table>
                        {foreach from=$volunteer_project.contacts item=person}
                          <tr>
                            <td>{$person.display_name}</td>
                            <td>{$person.email}</td>
                            <td>{$person.phone}</td>
                          </tr>
                        {/foreach}
                        </table>
                      </td>
                    </tr>

                    <tr>
                      <td>
                        <strong>Shifts:</strong>
                        <table border="1">
                          <tr>
                            <td>Role</td>
                            <td>Description</td>
                            <td>Start Time</td>
                            <td>Duration</td>
                          </tr>
                          {foreach from=$volunteer_project.opportunities item=opportunity}
                          <tr>
                            <td>{$opportunity.role}</td>
                            <td>{$opportunity.description}</td>
                            <td>{$opportunity.display_time}</td>
                            <td>{$opportunity.duration}</td>
                          </tr>
                          {/foreach}
                        </table>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              {/foreach}
            </table>

            <p>Thank you for your participation!</p>
          </td>
        </tr>
      </table>
    </center>
  </body>
</html>